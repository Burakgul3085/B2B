<?php

namespace App\Domain\Store\Invoicing;

use App\Models\Store\StoreEInvoice;
use App\Models\Store\StoreSetting;
use DOMDocument;
use DOMElement;

/**
 * UBL-TR 1.2 fatura belgesi (GİB e-Fatura / e-Arşiv biçimi). Entegratör (Uyumsoft) belgeyi imzalar, numarasını
 * verir ve GİB'e iletir; burada yalnızca içerik üretilir. Tutarlar InvoiceMath ile hesaplanmış kalemlerden gelir.
 *
 * Profil: e-Fatura mükellefi alıcıya TEMELFATURA, diğerlerine EARSIVFATURA. Tip: satışta SATIS, iadede IADE
 * (iade faturası, dayandığı satış faturasına BillingReference ile bağlanır).
 */
final class UblBuilder
{
    public const NS_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

    public const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    public const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private DOMDocument $doc;

    /**
     * @param  array{invoice_no: string, issue_date: string}|null  $reference  iade faturasında dayanılan satış faturası
     */
    public function build(StoreEInvoice $invoice, string $documentType, ?array $reference = null): DOMElement
    {
        $this->doc = new DOMDocument('1.0', 'UTF-8');
        $invoice->loadMissing('lines');
        $lines = $invoice->lines;
        $totals = InvoiceMath::totals($lines->map(fn ($l) => $l->only(['net_amount', 'vat_amount', 'line_total', 'vat_rate'])));
        $allowance = $lines->reduce(fn ($sum, $l) => bcadd($sum, $this->netDiscount($l), 2), '0.00');

        $root = $this->doc->createElementNS(self::NS_INVOICE, 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::NS_CAC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::NS_CBC);
        $this->doc->appendChild($root);

        $this->cbc($root, 'UBLVersionID', '2.1');
        $this->cbc($root, 'CustomizationID', 'TR1.2');
        $this->cbc($root, 'ProfileID', $documentType === 'e_fatura' ? 'TEMELFATURA' : 'EARSIVFATURA');
        $this->cbc($root, 'ID', (string) $invoice->invoice_no); // boş: numarayı entegratör verir
        $this->cbc($root, 'CopyIndicator', 'false');
        $this->cbc($root, 'UUID', (string) $invoice->ettn);
        $this->cbc($root, 'IssueDate', $invoice->issue_date->format('Y-m-d'));
        $this->cbc($root, 'IssueTime', now('Europe/Istanbul')->format('H:i:s'));
        $this->cbc($root, 'InvoiceTypeCode', $invoice->kind === 'refund' ? 'IADE' : 'SATIS');
        if ($invoice->note) {
            $this->cbc($root, 'Note', $invoice->note);
        }
        $this->cbc($root, 'Note', 'Sipariş no: '.$invoice->order->order_no);
        $this->cbc($root, 'DocumentCurrencyCode', 'TRY');
        $this->cbc($root, 'LineCountNumeric', (string) $lines->count());

        if ($reference) {
            $ref = $this->cac($this->cac($root, 'BillingReference'), 'InvoiceDocumentReference');
            $this->cbc($ref, 'ID', $reference['invoice_no']);
            $this->cbc($ref, 'IssueDate', $reference['issue_date']);
        }

        $this->supplier($root);
        $this->customer($root, $invoice);
        $this->taxTotal($root, $totals['vat_total'], $totals['by_rate']);

        $monetary = $this->cac($root, 'LegalMonetaryTotal');
        $this->amount($monetary, 'LineExtensionAmount', bcadd($totals['subtotal'], $allowance, 2));
        $this->amount($monetary, 'TaxExclusiveAmount', $totals['subtotal']);
        $this->amount($monetary, 'TaxInclusiveAmount', $totals['total']);
        $this->amount($monetary, 'AllowanceTotalAmount', $allowance);
        $this->amount($monetary, 'PayableAmount', $totals['total']);

        foreach ($lines->values() as $i => $line) {
            $this->line($root, $i + 1, $line);
        }

        return $root;
    }

    public function xml(StoreEInvoice $invoice, string $documentType, ?array $reference = null): string
    {
        $root = $this->build($invoice, $documentType, $reference);

        return (string) $this->doc->saveXML($root);
    }

    /** Satıcı: mağaza ayarlarındaki unvan, adres ve vergi bilgileri. Gönderici kimliği ayarda (yoksa vergi numarası). */
    private function supplier(DOMElement $root): void
    {
        $s = StoreSetting::values();
        $id = preg_replace('/\D/', '', (string) ($s['invoice_sender_id'] ?: $s['company_tax_number']));
        $party = $this->cac($this->cac($root, 'AccountingSupplierParty'), 'Party');
        $this->identification($party, $id);
        $this->cbc($this->cac($party, 'PartyName'), 'Name', $s['company_title'] ?: config('store.name'));
        // Adres "… ODUNPAZARI / ESKİŞEHİR" biçimindedir: son iki parça ilçe ve il olarak alınır.
        $parts = array_map('trim', explode('/', (string) $s['address']));
        $city = count($parts) > 1 ? array_pop($parts) : 'Eskişehir';
        $district = count($parts) > 0 && preg_match('/(\S+)$/u', end($parts), $m) ? $m[1] : null;
        $this->address($party, (string) $s['address'], $district, $city);
        $this->taxScheme($party, $s['company_tax_office']);
        $contact = $this->cac($party, 'Contact');
        if ($s['phone']) {
            $this->cbc($contact, 'Telephone', $s['phone']);
        }
        if ($s['email']) {
            $this->cbc($contact, 'ElectronicMail', $s['email']);
        }
        if (strlen($id) === 11) {
            $this->person($party, (string) ($s['company_title'] ?: config('store.name')));
        }
    }

    private function customer(DOMElement $root, StoreEInvoice $invoice): void
    {
        $individual = $invoice->buyer_type === 'individual';
        $id = $invoice->buyer_tax_number ?: ($individual ? '11111111111' : '');
        $party = $this->cac($this->cac($root, 'AccountingCustomerParty'), 'Party');
        $this->identification($party, $id);
        if (! $individual) {
            $this->cbc($this->cac($party, 'PartyName'), 'Name', $invoice->buyer_name);
        }
        $this->address($party, $invoice->buyer_address, $invoice->buyer_district, $invoice->buyer_city);
        $this->taxScheme($party, $invoice->buyer_tax_office);
        $contact = $this->cac($party, 'Contact');
        if ($invoice->buyer_phone) {
            $this->cbc($contact, 'Telephone', $invoice->buyer_phone);
        }
        if ($invoice->buyer_email) {
            $this->cbc($contact, 'ElectronicMail', $invoice->buyer_email);
        }
        if ($individual) {
            $this->person($party, $invoice->buyer_name);
        }
    }

    private function identification(DOMElement $party, string $id): void
    {
        $node = $this->cbc($this->cac($party, 'PartyIdentification'), 'ID', $id);
        $node->setAttribute('schemeID', strlen($id) === 11 ? 'TCKN' : 'VKN');
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
        $this->cbc($scheme, 'Name', trim(str_ireplace(['vergi dairesi', 'v.d.'], '', (string) $office)) ?: '-');
    }

    private function person(DOMElement $party, string $name): void
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
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
            // %0 KDV'de istisna kodu zorunludur; 351: istisna olmayan diğer (mali müşavirle teyit edilmeli).
            $this->cbc($category, 'TaxExemptionReasonCode', '351');
            $this->cbc($category, 'TaxExemptionReason', 'KDV - İstisna Olmayan Diğer');
        }
        $scheme = $this->cac($category, 'TaxScheme');
        $this->cbc($scheme, 'Name', 'KDV');
        $this->cbc($scheme, 'TaxTypeCode', '0015');
    }

    private function line(DOMElement $root, int $no, $line): void
    {
        $node = $this->cac($root, 'InvoiceLine');
        $this->cbc($node, 'ID', (string) $no);
        $this->cbc($node, 'InvoicedQuantity', rtrim(rtrim((string) $line->quantity, '0'), '.'))->setAttribute('unitCode', 'C62');
        $this->amount($node, 'LineExtensionAmount', (string) $line->net_amount);
        $discount = $this->netDiscount($line);
        if (bccomp($discount, '0', 2) > 0) {
            $charge = $this->cac($node, 'AllowanceCharge');
            $this->cbc($charge, 'ChargeIndicator', 'false');
            $this->amount($charge, 'Amount', $discount);
            $this->amount($charge, 'BaseAmount', bcadd((string) $line->net_amount, $discount, 2));
        }
        $tax = $this->cac($node, 'TaxTotal');
        $this->amount($tax, 'TaxAmount', (string) $line->vat_amount);
        $this->taxSubtotal($tax, (string) $line->net_amount, (string) $line->vat_amount, rtrim(rtrim((string) $line->vat_rate, '0'), '.'));
        $item = $this->cac($node, 'Item');
        $this->cbc($item, 'Name', $line->name);
        if ($line->sku) {
            $this->cbc($this->cac($item, 'SellersItemIdentification'), 'ID', $line->sku);
        }
        $this->amount($this->cac($node, 'Price'), 'PriceAmount', InvoiceMath::netUnit((string) $line->unit_price, (string) $line->vat_rate));
    }

    /** Satır indiriminin KDV hariç karşılığı: KDV hariç brüt − KDV hariç satır tutarı. */
    private function netDiscount($line): string
    {
        if (bccomp((string) $line->discount, '0', 2) <= 0) {
            return '0.00';
        }
        $gross = InvoiceMath::line((string) $line->quantity, (string) $line->unit_price, '0', (string) $line->vat_rate)['net_amount'];

        return bcsub($gross, (string) $line->net_amount, 2);
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
