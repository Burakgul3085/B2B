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
 * Kimlik doğrulaması WS-Security UsernameToken başlığıyla yapılır (Uyumsoft web servis kullanıcısı; portal kullanıcısından
 * ayrı olabilir). İstek yapısı servisin WSDL'ine göredir (?singleWsdl); test ortamında Uyumsoft'un ortak test hesabıyla
 * (VKN 9000068418) uçtan uca denendi.
 *
 * Kullanılan işlemler: WhoAmI (bağlantı denemesi), IsEInvoiceUser + GetUserAliasses (alıcı e-Fatura mükellefi mi,
 * posta kutusu etiketi), SendInvoice, GetOutboxInvoice (ETTN ile sorgu), GetOutboxInvoicePdf, CancelEArchiveInvoice.
 */
final class UyumsoftProvider implements EInvoiceProvider
{
    public const TEST_URL = 'https://efatura-test.uyumsoft.com.tr/Services/Integration';

    public const LIVE_URL = 'https://efatura.uyumsoft.com.tr/Services/Integration';

    private const NS = 'http://tempuri.org/';

    private const NS_WSSE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';

    private const PASSWORD_TEXT = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText';

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

        // Alıcının posta kutusu (PK) etiketi: e-Fatura bu etikete gönderilir. Açık etiketlerden "urn:mail:" ile başlayan
        // (GİB'deki tam etiket) tercih edilir.
        $aliases = $this->call('GetUserAliasses', '<tem:vknTckn>'.$this->e($taxNumber).'</tem:vknTckn>');
        $boxes = collect($this->all($aliases, 'ReceiverboxAliases'))
            ->filter(fn (DOMElement $box) => strtolower($box->getAttribute('Enabled')) !== 'false' && $box->getAttribute('Alias') !== '')
            ->map(fn (DOMElement $box) => $box->getAttribute('Alias'));
        $alias = $boxes->first(fn ($a) => str_starts_with($a, 'urn:mail:')) ?? $boxes->first();

        return ['registered' => true, 'alias' => $alias, 'title' => $this->first($aliases, 'Definition')?->getAttribute('Title') ?: null];
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
        // Öğe sırası WSDL'deki gibidir: Invoice, TargetCustomer, EArchiveInvoiceInfo, Scenario, CreateDateUtc.
        $body = '<tem:invoices><tem:InvoiceInfo LocalDocumentId="'.$this->e($invoice->local_id).'">'
            .'<tem:Invoice xmlns:cac="'.UblBuilder::NS_CAC.'" xmlns:cbc="'.UblBuilder::NS_CBC.'">'.$inner.'</tem:Invoice>'
            .$target.$earchive
            .'<tem:Scenario>'.($documentType === 'e_fatura' ? 'eInvoice' : 'eArchive').'</tem:Scenario>'
            .'<tem:CreateDateUtc>'.now('UTC')->format('Y-m-d\TH:i:s').'</tem:CreateDateUtc>'
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
        $result = $this->call('CancelEArchiveInvoice', '<tem:request InvoiceId="'.$this->e((string) $invoice->ettn).'" CancelDate="'.now('Europe/Istanbul')->format('Y-m-d\TH:i:s').'"/>', reject: true);
        if (strtolower($result->getAttribute('Value')) === 'false') {
            throw new EInvoiceRejected('Uyumsoft e-Arşiv faturayı iptal etmedi'.($result->getAttribute('Message') ? ': '.mb_substr($result->getAttribute('Message'), 0, 300) : '.'));
        }
    }

    public function test(): string
    {
        $result = $this->call('WhoAmI', '', reject: true);
        $customer = $this->first($result, 'Customer');
        $title = trim((string) ($customer ? $this->first($customer, 'Name')?->textContent : ''));
        $taxNumber = trim((string) ($customer ? $this->first($customer, 'VkTckNo')?->textContent : ''));
        $message = 'Uyumsoft bağlantısı başarılı ('.(StoreSetting::get('uyumsoft_mode') === 'live' ? 'canlı' : 'test').' ortam)'
            .($title !== '' ? ": {$title}" : '').($taxNumber !== '' ? ", VKN/TCKN {$taxNumber}" : '').'.';
        $sender = (string) preg_replace('/\D/', '', (string) (StoreSetting::get('invoice_sender_id') ?: StoreSetting::get('company_tax_number')));
        if ($taxNumber !== '' && $sender !== $taxNumber) {
            $message .= " DİKKAT: Gönderici VKN/TCKN ({$sender}) Uyumsoft hesabındakiyle aynı değil; faturalar reddedilir. Fatura Ayarları'nda {$taxNumber} yazın.";
        }

        return $message;
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

        // WSDL: InternetSalesInfo öğeleri (WebAddress, PaymentMidierName, PaymentType, PaymentDate, ShipmentInfo) bu sırayla.
        return '<tem:InternetSalesInfo>'
            .'<tem:WebAddress>'.$this->e('https://'.config('store.domain')).'</tem:WebAddress>'
            .'<tem:PaymentMidierName>'.($order->payment_method === 'card' ? 'iyzico' : '').'</tem:PaymentMidierName>'
            .'<tem:PaymentType>'.$type.'</tem:PaymentType>'
            .'<tem:PaymentDate>'.($order->paid_at ?? $order->created_at)->timezone('Europe/Istanbul')->format('Y-m-d\TH:i:s').'</tem:PaymentDate>'
            .'<tem:ShipmentInfo><tem:SendDate>'.($order->shipped_at ?? now())->timezone('Europe/Istanbul')->format('Y-m-d\TH:i:s').'</tem:SendDate>'
            .'<tem:Carier SenderName="'.$this->e(mb_substr((string) ($order->carrier ?: 'Mağaza teslimatı'), 0, 100)).'"/></tem:ShipmentInfo>'
            .'</tem:InternetSalesInfo>';
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
        $security = '<wsse:Security xmlns:wsse="'.self::NS_WSSE.'"><wsse:UsernameToken>'
            .'<wsse:Username>'.$this->e((string) StoreSetting::get('uyumsoft_username')).'</wsse:Username>'
            .'<wsse:Password Type="'.self::PASSWORD_TEXT.'">'.$this->e((string) StoreSetting::secret('uyumsoft_password')).'</wsse:Password>'
            .'</wsse:UsernameToken></wsse:Security>';
        $envelope = '<?xml version="1.0" encoding="utf-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="'.self::NS.'">'
            .'<soapenv:Header>'.$security.'</soapenv:Header><soapenv:Body><tem:'.$method.'>'.$args.'</tem:'.$method.'></soapenv:Body></soapenv:Envelope>';

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

    /** @return list<DOMElement> */
    private function all(DOMElement $node, string $name): array
    {
        $found = (new DOMXPath($node->ownerDocument))->query('.//*[local-name()="'.$name.'"]', $node);

        return array_values(array_filter(iterator_to_array($found), fn ($n) => $n instanceof DOMElement));
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
