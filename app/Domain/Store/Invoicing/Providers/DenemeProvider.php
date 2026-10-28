<?php

namespace App\Domain\Store\Invoicing\Providers;

use App\Domain\Store\Invoicing\InvoicePdf;
use App\Models\Store\StoreEInvoice;
use Illuminate\Support\Str;

/**
 * Deneme sürücüsü: Uyumsoft bilgileri girilmeden Faturalar akışını uçtan uca denemek için. Hiçbir yere bağlanmaz;
 * "DNM" ile başlayan numara, rastgele ETTN ve üzerinde "DENEME" yazan PDF üretir. Geçerli fatura değildir.
 * VKN'si 1111111111 olan alıcı e-Fatura mükellefi sayılır (e-Fatura yolunu denemek için).
 */
class DenemeProvider implements EInvoiceProvider
{
    public const EFATURA_TEST_VKN = '1111111111';

    public function __construct(private InvoicePdf $pdf) {}

    public function key(): string
    {
        return 'deneme';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function lookup(string $taxNumber): array
    {
        $registered = $taxNumber === self::EFATURA_TEST_VKN;

        return ['registered' => $registered, 'alias' => $registered ? 'urn:mail:defaultpk@deneme' : null, 'title' => null];
    }

    public function send(StoreEInvoice $invoice, string $documentType, ?string $alias): array
    {
        $prefix = $documentType === 'e_fatura' ? 'DNF' : 'DNM';
        $year = $invoice->issue_date->format('Y');
        $seq = StoreEInvoice::where('invoice_no', 'like', "{$prefix}{$year}%")->count() + 1;

        return $this->result($invoice, $prefix.$year.str_pad((string) $seq, 9, '0', STR_PAD_LEFT), (string) Str::uuid());
    }

    public function find(StoreEInvoice $invoice): ?array
    {
        return null; // deneme sürücüsünde gönderim hiçbir yere gitmediği için sonucu belirsiz kalan fatura kesilmemiştir
    }

    public function cancel(StoreEInvoice $invoice, string $reason): void {}

    public function test(): string
    {
        return 'Deneme sürücüsü çalışıyor. Faturalar hiçbir yere gönderilmez; gerçek fatura için Uyumsoft bilgilerini girin.';
    }

    private function result(StoreEInvoice $invoice, string $number, string $ettn): array
    {
        $invoice->invoice_no = $number;
        $invoice->ettn = $ettn;

        return ['invoice_no' => $number, 'ettn' => $ettn, 'pdf' => $this->pdf->render($invoice, 'DENEME — GEÇERLİ FATURA DEĞİLDİR')];
    }
}
