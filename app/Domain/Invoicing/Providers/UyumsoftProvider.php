<?php

namespace App\Domain\Invoicing\Providers;

use App\Domain\Invoicing\InvoiceSettings;
use App\Domain\Invoicing\UblBuilder;
use App\Models\Invoice;
use App\Models\OrderPayment;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * B2B faturalarının Uyumsoft entegrasyonu (SOAP "Integration" servisi). Bilgiler toptancının Fatura Ayarları'ndandır;
 * mağazanın Uyumsoft ayarlarıyla ilişkisi yoktur (aynı Uyumsoft hesabı kullanılsa bile ayrı girilir ve ayrı seriden kesilir).
 *
 *   test: https://efatura-test.uyumsoft.com.tr/Services/Integration
 *   canlı: https://edonusumapi.uyum.com.tr/Services/Integration
 *
 * Kimlik doğrulaması WS-Security UsernameToken ile yapılır (Uyumsoft web servis kullanıcısı). Fatura numarası seri
 * önekiyle istenir: UBL ID alanına seri (3 karakter) yazılırsa Uyumsoft numarayı o seriden verir; boşsa varsayılan seriden.
 * İşlemler: WhoAmI, IsEInvoiceUser + GetUserAliasses, SendInvoice, GetOutboxInvoice, QueryOutboxInvoiceStatus,
 * GetOutboxInvoicePdf, CancelEArchiveInvoice.
 */
final class UyumsoftProvider implements InvoiceProvider
{
    public const TEST_URL = 'https://efatura-test.uyumsoft.com.tr/Services/Integration';

    public const LIVE_URL = 'https://edonusumapi.uyum.com.tr/Services/Integration';

    private const NS = 'http://tempuri.org/';

    private const NS_WSSE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';

    private const PASSWORD_TEXT = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText';

    /** Uyumsoft InvoiceStatus değerlerinin karşılığı. */
    private const STATES = [
        'SentToGib' => 'ok', 'Approved' => 'ok', 'WaitingForAprovement' => 'ok',
        'NotPrepared' => 'pending', 'NotSend' => 'pending', 'Draft' => 'pending', 'Queued' => 'pending', 'Processing' => 'pending',
        'Error' => 'error', 'Declined' => 'error',
        'Canceled' => 'cancelled', 'EArchivedCanceled' => 'cancelled',
    ];

    public function __construct(private UblBuilder $ubl, private InvoiceSettings $settings) {}

    public function key(): string
    {
        return 'uyumsoft';
    }

    public function isConfigured(): bool
    {
        return filled($this->settings->record()->uyumsoft_username) && $this->settings->hasPassword();
    }

    public function url(): string
    {
        return $this->settings->record()->uyumsoft_mode === 'live' ? self::LIVE_URL : self::TEST_URL;
    }

    public function lookup(string $taxNumber): array
    {
        $registered = $this->call('IsEInvoiceUser', '<tem:vknTckn>'.$this->e($taxNumber).'</tem:vknTckn><tem:alias></tem:alias>');
        if (strtolower((string) $registered->getAttribute('Value')) !== 'true' && strtolower(trim($registered->textContent)) !== 'true') {
            return ['registered' => false, 'alias' => null, 'title' => null];
        }

        $aliases = $this->call('GetUserAliasses', '<tem:vknTckn>'.$this->e($taxNumber).'</tem:vknTckn>');
        $boxes = collect($this->all($aliases, 'ReceiverboxAliases'))
            ->filter(fn (DOMElement $box) => strtolower($box->getAttribute('Enabled')) !== 'false' && $box->getAttribute('Alias') !== '')
            ->map(fn (DOMElement $box) => $box->getAttribute('Alias'));
        $alias = $boxes->first(fn ($a) => str_starts_with($a, 'urn:mail:')) ?? $boxes->first();

        return ['registered' => true, 'alias' => $alias, 'title' => $this->first($aliases, 'Definition')?->getAttribute('Title') ?: null];
    }

    public function send(Invoice $invoice, string $documentType, ?string $alias): array
    {
        $record = $this->settings->record();
        $series = (string) ($documentType === 'e_fatura' ? $record->series_efatura : $record->series_earsiv);
        $ubl = $this->ubl->xml($invoice, $documentType, $this->reference($invoice), $series);
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
            .'<tem:CreateDateUtc>'.now('UTC')->format('Y-m-d\TH:i:s').'</tem:CreateDateUtc>'
            .'</tem:InvoiceInfo></tem:invoices>';

        $result = $this->call('SendInvoice', $body, reject: true);
        $value = $this->first($result, 'Value');
        $ettn = $value?->getAttribute('Id') ?: (string) $invoice->ettn;
        $number = $value?->getAttribute('Number');
        if (! $number) {
            throw new InvoiceUncertain('Uyumsoft faturayı aldı ama fatura numarası dönmedi; birkaç dakika sonra "Uyumsoft\'tan sorgula" ile kontrol edin.');
        }

        return ['invoice_no' => $number, 'ettn' => $ettn, 'pdf' => $this->pdf($ettn)];
    }

    public function find(Invoice $invoice): ?array
    {
        if (! $invoice->ettn) {
            return null;
        }
        try {
            $result = $this->call('GetOutboxInvoice', '<tem:invoiceId>'.$this->e($invoice->ettn).'</tem:invoiceId>', reject: true);
        } catch (InvoiceRejected) {
            return null; // Uyumsoft'ta bu ETTN ile fatura yok: kesilmemiş
        }
        $number = $this->first($result, 'Value')?->getAttribute('InvoiceId') ?: $this->first($result, 'ID')?->textContent;

        return $number ? ['invoice_no' => $number, 'ettn' => $invoice->ettn, 'pdf' => $this->pdf($invoice->ettn)] : null;
    }

    public function status(Invoice $invoice): ?array
    {
        if (! $invoice->ettn) {
            return null;
        }
        $result = $this->call('QueryOutboxInvoiceStatus', '<tem:invoiceIds><tem:string>'.$this->e($invoice->ettn).'</tem:string></tem:invoiceIds>', reject: true);
        $value = $this->first($result, 'Value');
        $status = (string) $value?->getAttribute('Status');
        if ($status === '') {
            return null;
        }

        return ['state' => self::STATES[$status] ?? 'other', 'status' => $status, 'message' => trim((string) $value->getAttribute('Message')) ?: null];
    }

    public function cancel(Invoice $invoice, string $reason): void
    {
        $result = $this->call('CancelEArchiveInvoice', '<tem:request InvoiceId="'.$this->e((string) $invoice->ettn).'" CancelDate="'.now('Europe/Istanbul')->format('Y-m-d\TH:i:s').'"/>', reject: true);
        if (strtolower($result->getAttribute('Value')) === 'false') {
            throw new InvoiceRejected('Uyumsoft e-Arşiv faturayı iptal etmedi'.($result->getAttribute('Message') ? ': '.mb_substr($result->getAttribute('Message'), 0, 300) : '.'));
        }
    }

    public function test(): string
    {
        $result = $this->call('WhoAmI', '', reject: true);
        $customer = $this->first($result, 'Customer');
        $title = trim((string) ($customer ? $this->first($customer, 'Name')?->textContent : ''));
        $taxNumber = trim((string) ($customer ? $this->first($customer, 'VkTckNo')?->textContent : ''));
        $message = 'Uyumsoft bağlantısı başarılı ('.($this->settings->record()->uyumsoft_mode === 'live' ? 'canlı' : 'test').' ortam)'
            .($title !== '' ? ": {$title}" : '').($taxNumber !== '' ? ", VKN/TCKN {$taxNumber}" : '').'.';
        $sender = $this->settings->seller()['id'];
        if ($taxNumber !== '' && $sender !== $taxNumber) {
            $message .= " DİKKAT: Gönderici VKN/TCKN ({$sender}) Uyumsoft hesabındakiyle aynı değil; faturalar reddedilir. Fatura Ayarları'nda {$taxNumber} yazın.";
        }

        return $message;
    }

    /** İade faturasında dayanılan satış faturası (numara ve tarih). */
    private function reference(Invoice $invoice): ?array
    {
        if ($invoice->kind !== 'refund') {
            return null;
        }
        $sale = Invoice::where('order_id', $invoice->order_id)->where('kind', 'sale')->where('status', 'sent')->latest('id')->first();

        return $sale ? ['invoice_no' => $sale->invoice_no, 'issue_date' => $sale->issue_date->format('Y-m-d')] : null;
    }

    /**
     * e-Arşiv internet satışı bilgileri (VUK 509): sipariş bayi panelinden verildiği için her B2B e-Arşiv faturası internet
     * satışıdır. Ödeme şekli siparişin gerçek ödemesinden gelir (kart / havale); ödeme şekli belli olmayan (sonra öde,
     * cariden mahsup) satışta GİB'in önerdiği KAPIDAODEME yazılır. Taşıyıcı: malı toptancı kendisi teslim eder.
     */
    private function internetSale(Invoice $invoice): string
    {
        $order = $invoice->order;
        $payment = OrderPayment::where('order_id', $order->id)->whereIn('status', ['succeeded', 'refunded'])->latest('id')->first();
        $type = match (true) {
            $payment?->driver === 'iyzico' => 'KREDIKARTI/BANKAKARTI',
            $payment?->driver === 'manual' && $payment->method === 'transfer' => 'EFT/HAVALE',
            default => 'KAPIDAODEME',
        };
        $paidAt = $order->paid_at ?? $invoice->issue_date;
        $seller = $this->settings->seller();

        return '<tem:InternetSalesInfo>'
            .'<tem:WebAddress>'.$this->e($seller['web']).'</tem:WebAddress>'
            .'<tem:PaymentMidierName>'.($type === 'KREDIKARTI/BANKAKARTI' ? 'iyzico' : '').'</tem:PaymentMidierName>'
            .'<tem:PaymentType>'.$type.'</tem:PaymentType>'
            .'<tem:PaymentDate>'.$paidAt->copy()->timezone('Europe/Istanbul')->format('Y-m-d\TH:i:s').'</tem:PaymentDate>'
            .'<tem:ShipmentInfo><tem:SendDate>'.($order->shipped_at ?? now())->copy()->timezone('Europe/Istanbul')->format('Y-m-d\TH:i:s').'</tem:SendDate>'
            .'<tem:Carier SenderTcknVkn="'.$this->e($seller['id']).'" SenderName="'.$this->e(mb_substr($seller['name'], 0, 100)).'"/></tem:ShipmentInfo>'
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
     * SOAP isteği. Başarısız yanıt (IsSucceded=false veya SOAP Fault): reject ise InvoiceRejected, değilse InvoiceUncertain.
     *
     * @throws InvoiceRejected|InvoiceUncertain
     */
    private function call(string $method, string $args, bool $reject = false): DOMElement
    {
        if (! $this->isConfigured()) {
            throw new InvoiceRejected('Uyumsoft kullanıcı adı ve şifresi girilmemiş (Ayarlar → Fatura Ayarları).');
        }
        $security = '<wsse:Security xmlns:wsse="'.self::NS_WSSE.'"><wsse:UsernameToken>'
            .'<wsse:Username>'.$this->e((string) $this->settings->record()->uyumsoft_username).'</wsse:Username>'
            .'<wsse:Password Type="'.self::PASSWORD_TEXT.'">'.$this->e((string) $this->settings->password()).'</wsse:Password>'
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
            throw new InvoiceUncertain('Uyumsoft\'a bağlanılamadı.');
        }

        $doc = new DOMDocument;
        if (! @$doc->loadXML((string) $response->body(), LIBXML_NONET | LIBXML_NOCDATA)) {
            throw new InvoiceUncertain('Uyumsoft\'tan okunamayan yanıt geldi (HTTP '.$response->status().').');
        }
        $xpath = new DOMXPath($doc);
        if ($fault = $xpath->query('//*[local-name()="Fault"]/*[local-name()="faultstring"]')->item(0)) {
            throw new InvoiceRejected('Uyumsoft: '.mb_substr(trim($fault->textContent), 0, 400));
        }
        $result = $xpath->query('//*[local-name()="'.$method.'Result"]')->item(0);
        if (! $result instanceof DOMElement) {
            throw new InvoiceUncertain('Uyumsoft yanıtında sonuç bulunamadı.');
        }
        if (strtolower($result->getAttribute('IsSucceded')) === 'false') {
            $message = 'Uyumsoft: '.mb_substr($result->getAttribute('Message') ?: 'işlem başarısız', 0, 400);
            throw $reject ? new InvoiceRejected($message) : new InvoiceUncertain($message);
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
