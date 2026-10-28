<?php

namespace App\Domain\Store\Invoicing\Providers;

use App\Domain\Store\Invoicing\UblBuilder;
use App\Models\Store\StoreEInvoice;
use App\Models\Store\StoreSetting;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Uyumsoft e-Fatura / e-Arşiv entegrasyonu (SOAP "Integration" servisi). Fatura UBL-TR olarak üretilir
 * (UblBuilder), Uyumsoft imzalar, numaralandırır ve GİB'e iletir.
 *
 *   test: https://efatura-test.uyumsoft.com.tr/Services/Integration
 *   canlı: https://efatura.uyumsoft.com.tr/Services/Integration
 *
 * Kullanılan işlemler: WhoAmI (bağlantı denemesi), IsEInvoiceUser + GetUserAliasses (alıcı e-Fatura mükellefi mi,
 * posta kutusu etiketi), SendInvoice, GetOutboxInvoice (ETTN ile sorgu), GetOutboxInvoicePdf, CancelEArchiveInvoice.
 * NOT: İşlem ve alan adları Uyumsoft'un Integration servisine göre yazıldı; Uyumsoft test hesabı bilgileri gelince
 * test ortamında uçtan uca doğrulanmalıdır (docs/26-fatura-uyumsoft.md).
 */
final class UyumsoftProvider implements EInvoiceProvider
{
    public const TEST_URL = 'https://efatura-test.uyumsoft.com.tr/Services/Integration';

    public const LIVE_URL = 'https://efatura.uyumsoft.com.tr/Services/Integration';

    private const NS = 'http://tempuri.org/';

    public function __construct(private UblBuilder $ubl) {}

    public function key(): string
    {
        return 'uyumsoft';
    }

    public function isConfigured(): bool
    {
        return filled(StoreSetting::get('uyumsoft_username')) && filled(StoreSetting::secret('uyumsoft_password'));
    }

    public function url(): string
    {
        return StoreSetting::get('uyumsoft_mode') === 'live' ? self::LIVE_URL : self::TEST_URL;
    }

    public function lookup(string $taxNumber): array
    {
        $registered = $this->call('IsEInvoiceUser', '<tem:vknTckn>'.$this->e($taxNumber).'</tem:vknTckn><tem:alias></tem:alias>');
        if (strtolower((string) $registered->getAttribute('Value')) !== 'true' && strtolower(trim($registered->textContent)) !== 'true') {
            return ['registered' => false, 'alias' => null, 'title' => null];
        }

        // Alıcının posta kutusu (PK) etiketi: e-Fatura bu etikete gönderilir.
        $aliases = $this->call('GetUserAliasses', '<tem:vknTckn>'.$this->e($taxNumber).'</tem:vknTckn>');
        $box = $this->first($aliases, 'Receiverboxes') ?? $this->first($aliases, 'ReceiverboxAlias');

        return ['registered' => true, 'alias' => $box?->getAttribute('Alias') ?: ($box?->textContent ?: null), 'title' => $this->first($aliases, 'Value')?->getAttribute('Title') ?: null];
    }

    public function send(StoreEInvoice $invoice, string $documentType, ?string $alias): array
    {
        $reference = $this->reference($invoice);
        $ubl = $this->ubl->xml($invoice, $documentType, $reference);
        // UBL kök öğesi Invoice'tur; Uyumsoft InvoiceInfo içinde "Invoice" öğesinin altını bekler.
        $inner = preg_replace(['#^<Invoice[^>]*>#', '#</Invoice>$#'], '', trim($ubl));
        $earchive = $documentType === 'e_arsiv'
            ? '<tem:EArchiveInvoiceInfo DeliveryType="Electronic">'.$this->internetSale($invoice).'</tem:EArchiveInvoiceInfo>'
            : '';
        $target = $documentType === 'e_fatura'
            ? '<tem:TargetCustomer VknTckn="'.$this->e((string) $invoice->buyer_tax_number).'" Alias="'.$this->e((string) $alias).'" Title="'.$this->e($invoice->buyer_name).'"/>'
            : '';
        $body = '<tem:invoices><tem:InvoiceInfo LocalDocumentId="'.$this->e($invoice->local_id).'">'
            .'<tem:Invoice xmlns:cac="'.UblBuilder::NS_CAC.'" xmlns:cbc="'.UblBuilder::NS_CBC.'">'.$inner.'</tem:Invoice>'
            .$target.$earchive
            .'<tem:Scenario>'.($documentType === 'e_fatura' ? 'eInvoice' : 'eArchive').'</tem:Scenario>'
            .'</tem:InvoiceInfo></tem:invoices>';

        $result = $this->call('SendInvoice', $body, reject: true);
        $value = $this->first($result, 'Value');
        $ettn = $value?->getAttribute('Id') ?: (string) $invoice->ettn;
        $number = $value?->getAttribute('Number');
        if (! $number) {
            throw new EInvoiceUncertain('Uyumsoft faturayı aldı ama fatura numarası dönmedi; birkaç dakika sonra "Uyumsoft\'tan sorgula" ile kontrol edin.');
        }

        return ['invoice_no' => $number, 'ettn' => $ettn, 'pdf' => $this->pdf($ettn)];
    }

    public function find(StoreEInvoice $invoice): ?array
    {
        if (! $invoice->ettn) {
            return null;
        }
        try {
            $result = $this->call('GetOutboxInvoice', '<tem:invoiceId>'.$this->e($invoice->ettn).'</tem:invoiceId>', reject: true);
        } catch (EInvoiceRejected) {
            return null; // Uyumsoft'ta bu ETTN ile fatura yok: kesilmemiş
        }
        $number = $this->first($result, 'Value')?->getAttribute('InvoiceId') ?: $this->first($result, 'ID')?->textContent;
        if (! $number) {
            return null;
        }

        return ['invoice_no' => $number, 'ettn' => $invoice->ettn, 'pdf' => $this->pdf($invoice->ettn)];
    }

    public function cancel(StoreEInvoice $invoice, string $reason): void
    {
        $this->call('CancelEArchiveInvoice', '<tem:invoiceId>'.$this->e((string) $invoice->ettn).'</tem:invoiceId>', reject: true);
    }

    public function test(): string
    {
        $result = $this->call('WhoAmI', '', reject: true);
        $title = $this->first($result, 'Value')?->getAttribute('Title') ?: $this->first($result, 'Title')?->textContent;

        return 'Uyumsoft bağlantısı başarılı ('.(StoreSetting::get('uyumsoft_mode') === 'live' ? 'canlı' : 'test').' ortam)'.($title ? ": {$title}" : '').'.';
    }

    /** İade faturasında dayanılan satış faturası (numara ve tarih). */
    private function reference(StoreEInvoice $invoice): ?array
    {
        if ($invoice->kind !== 'refund') {
            return null;
        }
        $sale = $invoice->order->invoice()->first();

        return $sale ? ['invoice_no' => $sale->invoice_no, 'issue_date' => $sale->issued_on->format('Y-m-d')] : null;
    }

    /** e-Arşiv internet satışı bilgileri (GİB: web adresi, ödeme şekli ve tarihi, gönderim). */
    private function internetSale(StoreEInvoice $invoice): string
    {
        $order = $invoice->order;
        $type = match ($order->payment_method) {
            'card' => 'KREDIKARTI/BANKAKARTI',
            'transfer' => 'EFT/HAVALE',
            'cod' => 'KAPIDAODEME',
            default => 'DIGER',
        };

        return '<tem:InternetSalesInfo WebAddress="'.$this->e('https://'.config('store.domain')).'" PaymentMidierName="'.($order->payment_method === 'card' ? 'iyzico' : '').'"'
            .' PaymentType="'.$type.'" PaymentDate="'.($order->paid_at ?? $order->created_at)->timezone('Europe/Istanbul')->format('Y-m-d').'"/>'
            .'<tem:ShipmentInfo SendDate="'.($order->shipped_at ?? now())->timezone('Europe/Istanbul')->format('Y-m-d').'" Carrier="'.$this->e((string) ($order->carrier ?: 'Mağaza teslimatı')).'"/>';
    }

    private function pdf(string $ettn): string
    {
        try {
            $result = $this->call('GetOutboxInvoicePdf', '<tem:invoiceId>'.$this->e($ettn).'</tem:invoiceId>', reject: true);
            $data = $this->first($result, 'Data')?->textContent ?? $this->first($result, 'Value')?->textContent;
            $pdf = $data ? base64_decode(trim($data), true) : false;

            return $pdf && str_starts_with($pdf, '%PDF') ? $pdf : '';
        } catch (Throwable) {
            return ''; // fatura kesildi; PDF sonradan "PDF'i yeniden al" ile indirilebilir
        }
    }

    /**
     * SOAP isteği. Başarısız yanıt (IsSucceded=false veya SOAP Fault): reject ise EInvoiceRejected, değilse EInvoiceUncertain.
     *
     * @throws EInvoiceRejected|EInvoiceUncertain
     */
    private function call(string $method, string $args, bool $reject = false): DOMElement
    {
        if (! $this->isConfigured()) {
            throw new EInvoiceRejected('Uyumsoft kullanıcı adı ve şifresi girilmemiş (Ayarlar → Fatura).');
        }
        $user = '<tem:userInfo Username="'.$this->e((string) StoreSetting::get('uyumsoft_username')).'" Password="'.$this->e((string) StoreSetting::secret('uyumsoft_password')).'"/>';
        $envelope = '<?xml version="1.0" encoding="utf-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="'.self::NS.'">'
            .'<soapenv:Header/><soapenv:Body><tem:'.$method.'>'.$user.$args.'</tem:'.$method.'></soapenv:Body></soapenv:Envelope>';

        try {
            $response = Http::timeout(60)->withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => '"'.self::NS.'IIntegration/'.$method.'"',
            ])->withBody($envelope, 'text/xml; charset=utf-8')->post($this->url());
        } catch (Throwable) {
            throw new EInvoiceUncertain('Uyumsoft\'a bağlanılamadı.');
        }

        $doc = new DOMDocument;
        if (! @$doc->loadXML((string) $response->body(), LIBXML_NONET | LIBXML_NOCDATA)) {
            throw new EInvoiceUncertain('Uyumsoft\'tan okunamayan yanıt geldi (HTTP '.$response->status().').');
        }
        $xpath = new DOMXPath($doc);
        if ($fault = $xpath->query('//*[local-name()="Fault"]/*[local-name()="faultstring"]')->item(0)) {
            throw new EInvoiceRejected('Uyumsoft: '.mb_substr(trim($fault->textContent), 0, 400));
        }
        $result = $xpath->query('//*[local-name()="'.$method.'Result"]')->item(0);
        if (! $result instanceof DOMElement) {
            throw new EInvoiceUncertain('Uyumsoft yanıtında sonuç bulunamadı.');
        }
        if (strtolower($result->getAttribute('IsSucceded')) === 'false') {
            $message = 'Uyumsoft: '.mb_substr($result->getAttribute('Message') ?: 'işlem başarısız', 0, 400);
            throw $reject ? new EInvoiceRejected($message) : new EInvoiceUncertain($message);
        }

        return $result;
    }

    private function first(DOMElement $node, string $name): ?DOMElement
    {
        $found = (new DOMXPath($node->ownerDocument))->query('.//*[local-name()="'.$name.'"]', $node)->item(0);

        return $found instanceof DOMElement ? $found : null;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
