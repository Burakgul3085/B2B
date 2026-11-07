<?php

namespace App\Domain\Invoicing;

use App\Models\Invoice;
use DOMDocument;
use DOMElement;

/**
 * B2B faturasının UBL-TR 1.2 belgesi (GİB e-Fatura / e-Arşiv biçimi). Uyumsoft imzalar, numaralandırır ve GİB'e iletir.
 * Mağazanın UBL üreticisinden ayrıdır: satıcı toptancının işletme kaydı / Fatura Ayarları'dır; tutarlar KDV hariçtir ve
 * satır iskontosu (fiyat listesi indirimi + kampanya payı) her kalemde ayrıca gösterilir.
 *
 * Profil: e-Fatura mükellefi bayiye TEMELFATURA, diğerlerine EARSIVFATURA. Tip: SATIS, %0 KDV'li kalem varsa ISTISNA,
 * iadede IADE (dayandığı satış faturasına BillingReference / DocumentTypeCode IADE ile bağlanır).
 */
final class UblBuilder
{
    public const NS_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

    public const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    public const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    /** %0 KDV istisna kodu: KDV 13/n md. basılı kitap ve süreli yayınların teslimleri (mali müşavir kararı). */
    public const EXEMPTION_CODE = '335';

    public const EXEMPTION_REASON = 'KDV 13/n md. Basılı Kitap ve Süreli Yayınların Teslimleri';

    /** Ürün birim kodu → UN/ECE birim kodu (bilinmeyen: adet). */
    public const UNIT_CODES = ['ADET' => 'C62', 'AD' => 'C62', 'KG' => 'KGM', 'GR' => 'GRM', 'G' => 'GRM', 'M' => 'MTR', 'MT' => 'MTR',
        'LT' => 'LTR', 'L' => 'LTR', 'PAKET' => 'PA', 'PK' => 'PA', 'KUTU' => 'BX', 'SET' => 'SET', 'TAKIM' => 'SET', 'DÜZİNE' => 'DZN', 'DUZINE' => 'DZN'];

    public const PAYMENT_NOTES = ['card' => 'Kredi / banka kartı', 'transfer' => 'Havale / EFT', 'pay_later' => 'Vadeli (sonra öde)'];

    private DOMDocument $doc;

    public function __construct(private InvoiceSettings $settings) {}

    /**
     * @param  array{invoice_no: string, issue_date: string}|null  $reference  iade faturasında dayanılan satış faturası
     * @param  string  $id  fatura numarası alanı: boşsa sağlayıcı varsayılan seriden numara verir
     */
    public function xml(Invoice $invoice, string $documentType, ?array $reference = null, string $id = ''): string
    {
        $root = $this->build($invoice, $documentType, $reference, $id);

        return (string) $this->doc->saveXML($root);
    }

    public function build(Invoice $invoice, string $documentType, ?array $reference = null, string $id = ''): DOMElement
    {
        $this->doc = new DOMDocument('1.0', 'UTF-8');
        $invoice->loadMissing(['lines', 'order']);
        $lines = $invoice->lines;
        $totals = InvoiceMath::totals($lines->map(fn ($l) => $l->only(['net_amount', 'vat_amount', 'line_total', 'vat_rate'])));
        $seller = $this->settings->seller();

        $root = $this->doc->createElementNS(self::NS_INVOICE, 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::NS_CAC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::NS_CBC);
        $this->doc->appendChild($root);

        $this->cbc($root, 'UBLVersionID', '2.1');
        $this->cbc($root, 'CustomizationID', 'TR1.2');
        $this->cbc($root, 'ProfileID', $documentType === 'e_fatura' ? 'TEMELFATURA' : 'EARSIVFATURA');
        $this->cbc($root, 'ID', $id);
        $this->cbc($root, 'CopyIndicator', 'false');
        $this->cbc($root, 'UUID', (string) $invoice->ettn);
        $this->cbc($root, 'IssueDate', $invoice->issue_date->format('Y-m-d'));
        $this->cbc($root, 'IssueTime', now('Europe/Istanbul')->format('H:i:s'));
        $this->cbc($root, 'InvoiceTypeCode', self::typeCode($invoice->kind, $lines->pluck('vat_rate')->all()));
        if ($invoice->note) {
            $this->cbc($root, 'Note', $invoice->note);
        }
        $this->cbc($root, 'Note', 'Sipariş no: '.$invoice->order->order_no);
        if ($invoice->kind === 'sale' && isset(self::PAYMENT_NOTES[$invoice->order->payment_method])) {
            $this->cbc($root, 'Note', 'Ödeme şekli: '.self::PAYMENT_NOTES[$invoice->order->payment_method]);
        }
        $this->cbc($root, 'DocumentCurrencyCode', 'TRY');
        $this->cbc($root, 'LineCountNumeric', (string) $lines->count());

        if ($reference) {
            $ref = $this->cac($this->cac($root, 'BillingReference'), 'InvoiceDocumentReference');
            $this->cbc($ref, 'ID', $reference['invoice_no']);
            $this->cbc($ref, 'IssueDate', $reference['issue_date']);
            // GİB şematron kuralı: IADE tipli faturada dayanılan faturanın belge tipi kodu "IADE" olmalıdır.
            $this->cbc($ref, 'DocumentTypeCode', 'IADE');
        }

        $this->signature($root, $seller);
        $this->supplier($root, $seller);
        $this->customer($root, $invoice);
        $this->taxTotal($root, $totals['vat_total'], $totals['by_rate']);

        $monetary = $this->cac($root, 'LegalMonetaryTotal');
        $this->amount($monetary, 'LineExtensionAmount', $totals['subtotal']);
        $this->amount($monetary, 'TaxExclusiveAmount', $totals['subtotal']);
        $this->amount($monetary, 'TaxInclusiveAmount', $totals['total']);
        $this->amount($monetary, 'AllowanceTotalAmount', '0.00');
        $this->amount($monetary, 'PayableAmount', $totals['total']);

        foreach ($lines->values() as $i => $line) {
            $this->line($root, $i + 1, $line);
        }

        return $root;
    }

    /** @param  list<string|int|float>  $vatRates */
    public static function typeCode(string $kind, array $vatRates): string
    {
        if ($kind === 'refund') {
            return 'IADE';
        }
        foreach ($vatRates as $rate) {
            if (bccomp((string) $rate, '0', 2) === 0) {
                return 'ISTISNA';
            }
        }

        return 'SATIS';
    }

    public static function unitCode(?string $code): string
    {
        return self::UNIT_CODES[mb_strtoupper(trim((string) $code), 'UTF-8')] ?? 'C62';
    }

    private function signature(DOMElement $root, array $seller): void
    {
        $signature = $this->cac($root, 'Signature');
        $this->cbc($signature, 'ID', $seller['id'])->setAttribute('schemeID', 'VKN_TCKN');
        $party = $this->cac($signature, 'SignatoryParty');
        $this->identification($party, $seller['id']);
        $this->address($party, $seller['address'], $seller['district'], $seller['city']);
        $this->cbc($this->cac($this->cac($signature, 'DigitalSignatureAttachment'), 'ExternalReference'), 'URI', '#Signature_'.$seller['id']);
    }

    private function supplier(DOMElement $root, array $seller): void
    {
        $party = $this->cac($this->cac($root, 'AccountingSupplierParty'), 'Party');
        $this->cbc($party, 'WebsiteURI', $seller['web']);
        $this->identification($party, $seller['id']);
        // Şahıs firmasında (TCKN) satıcı Person ile yazılır; PartyName de eklenirse faturada ad iki kez çıkar.
        if (strlen($seller['id']) !== 11) {
            $this->cbc($this->cac($party, 'PartyName'), 'Name', $seller['name']);
        }
        $this->address($party, $seller['address'], $seller['district'], $seller['city']);
        $this->taxScheme($party, $seller['tax_office']);
        $contact = $this->cac($party, 'Contact');
        if ($seller['phone']) {
            $this->cbc($contact, 'Telephone', $seller['phone']);
        }
        if ($seller['email']) {
            $this->cbc($contact, 'ElectronicMail', $seller['email']);
        }
        if (strlen($seller['id']) === 11) {
            $this->person($party, $seller['name']);
        }
    }

    /** Alıcı: bayinin işletmesi. VKN (10 hane) tüzel kişi; TCKN (11 hane) şahıs firması (kişi adı da yazılır). */
    private function customer(DOMElement $root, Invoice $invoice): void
    {
        $id = (string) $invoice->buyer_tax_number;
        $party = $this->cac($this->cac($root, 'AccountingCustomerParty'), 'Party');
        $this->identification($party, $id);
        $this->cbc($this->cac($party, 'PartyName'), 'Name', $invoice->buyer_name);
        $this->address($party, $invoice->buyer_address, $invoice->buyer_district, $invoice->buyer_city);
        $this->taxScheme($party, $invoice->buyer_tax_office);
        $contact = $this->cac($party, 'Contact');
        if ($invoice->buyer_phone) {
            $this->cbc($contact, 'Telephone', $invoice->buyer_phone);
        }
        if ($invoice->buyer_email) {
            $this->cbc($contact, 'ElectronicMail', $invoice->buyer_email);
        }
        if (strlen($id) === 11) {
            $this->person($party, $invoice->buyer_name);
        }
    }

    private function identification(DOMElement $party, string $id): void
    {
        $this->cbc($this->cac($party, 'PartyIdentification'), 'ID', $id)->setAttribute('schemeID', strlen($id) === 11 ? 'TCKN' : 'VKN');
    }

    private function address(DOMElement $party, string $street, ?string $district, string $city): void
    {
        $address = $this->cac($party, 'PostalAddress');
        $this->cbc($address, 'StreetName', mb_substr($street, 0, 250));
        $this->cbc($address, 'CitySubdivisionName', $district ?: $city);
        $this->cbc($address, 'CityName', $city);
        $this->cbc($this->cac($address, 'Country'), 'Name', 'Türkiye');
    }

    private function taxScheme(DOMElement $party, ?string $office): void
    {
        $scheme = $this->cac($this->cac($party, 'PartyTaxScheme'), 'TaxScheme');
        $this->cbc($scheme, 'Name', trim(str_ireplace(['vergi dairesi başkanlığı', 'vergi dairesi', 'v.d.'], '', (string) $office)) ?: '-');
    }

    private function person(DOMElement $party, string $name): void
    {
        // "Ad Soyad – İşletme adı" biçimindeki unvandan yalnızca ad soyad alınır.
        $parts = preg_split('/\s+/u', trim(preg_split('/\s+[–—-]\s+/u', trim($name))[0])) ?: [];
        $family = count($parts) > 1 ? array_pop($parts) : '-';
        $person = $this->cac($party, 'Person');
        $this->cbc($person, 'FirstName', implode(' ', $parts) ?: $name);
        $this->cbc($person, 'FamilyName', $family);
    }

    /** @param  array<string, array{net: string, vat: string}>  $byRate */
    private function taxTotal(DOMElement $parent, string $vat, array $byRate): void
    {
        $total = $this->cac($parent, 'TaxTotal');
        $this->amount($total, 'TaxAmount', $vat);
        foreach ($byRate as $rate => $sum) {
            $this->taxSubtotal($total, $sum['net'], $sum['vat'], (string) $rate);
        }
    }

    private function taxSubtotal(DOMElement $total, string $net, string $vat, string $rate): void
    {
        $sub = $this->cac($total, 'TaxSubtotal');
        $this->amount($sub, 'TaxableAmount', $net);
        $this->amount($sub, 'TaxAmount', $vat);
        $this->cbc($sub, 'Percent', $rate);
        $category = $this->cac($sub, 'TaxCategory');
        if (bccomp($rate, '0', 2) === 0) {
            $this->cbc($category, 'TaxExemptionReasonCode', self::EXEMPTION_CODE);
            $this->cbc($category, 'TaxExemptionReason', self::EXEMPTION_REASON);
        }
        $scheme = $this->cac($category, 'TaxScheme');
        $this->cbc($scheme, 'Name', 'KDV');
        $this->cbc($scheme, 'TaxTypeCode', '0015');
    }

    /** Kalem: miktar × KDV hariç birim fiyat − iskonto = KDV hariç satır tutarı (UBL öğe sırasına göre). */
    private function line(DOMElement $root, int $no, $line): void
    {
        $node = $this->cac($root, 'InvoiceLine');
        $this->cbc($node, 'ID', (string) $no);
        $this->cbc($node, 'InvoicedQuantity', rtrim(rtrim((string) $line->quantity, '0'), '.'))->setAttribute('unitCode', self::unitCode($line->unit_code));
        $this->amount($node, 'LineExtensionAmount', (string) $line->net_amount);
        if (bccomp((string) $line->discount, '0', 2) > 0) {
            $allowance = $this->cac($node, 'AllowanceCharge');
            $this->cbc($allowance, 'ChargeIndicator', 'false');
            $this->amount($allowance, 'Amount', bcadd((string) $line->discount, '0', 2));
            $this->amount($allowance, 'BaseAmount', bcadd((string) $line->net_amount, (string) $line->discount, 2));
        }
        $tax = $this->cac($node, 'TaxTotal');
        $this->amount($tax, 'TaxAmount', (string) $line->vat_amount);
        $this->taxSubtotal($tax, (string) $line->net_amount, (string) $line->vat_amount, InvoiceMath::rate((string) $line->vat_rate));
        $item = $this->cac($node, 'Item');
        $this->cbc($item, 'Name', mb_substr($line->name, 0, 250));
        if ($line->sku) {
            $this->cbc($this->cac($item, 'SellersItemIdentification'), 'ID', $line->sku);
        }
        $this->amount($this->cac($node, 'Price'), 'PriceAmount', self::price((string) $line->unit_price));
    }

    /** Birim fiyat en az 2, en fazla 4 ondalıkla ("12.5000" → "12.50", "0.8333" → "0.8333"). */
    public static function price(string $value): string
    {
        $value = bcadd($value, '0', 4);
        $trimmed = rtrim(rtrim($value, '0'), '.');
        $decimals = str_contains($trimmed, '.') ? strlen(substr(strrchr($trimmed, '.'), 1)) : 0;

        return $decimals >= 2 ? $trimmed : bcadd($value, '0', 2);
    }

    private function cac(DOMElement $parent, string $name): DOMElement
    {
        return $parent->appendChild($this->doc->createElementNS(self::NS_CAC, 'cac:'.$name));
    }

    private function cbc(DOMElement $parent, string $name, string $value): DOMElement
    {
        $node = $this->doc->createElementNS(self::NS_CBC, 'cbc:'.$name);
        $node->appendChild($this->doc->createTextNode($value));

        return $parent->appendChild($node);
    }

    private function amount(DOMElement $parent, string $name, string $value): void
    {
        $this->cbc($parent, $name, $value)->setAttribute('currencyID', 'TRY');
    }
}
