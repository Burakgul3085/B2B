<?php

namespace App\Domain\Invoicing\Providers;

use App\Domain\Invoicing\InvoicePdf;
use App\Models\Invoice;
use Illuminate\Support\Str;

/**
 * Deneme sürücüsü: Uyumsoft bilgileri girilmeden B2B fatura akışını uçtan uca denemek için. Hiçbir yere bağlanmaz;
 * "DNM" (e-Arşiv) / "DNF" (e-Fatura) ile başlayan numara, rastgele ETTN ve üzerinde "DENEME" yazan PDF üretir.
 * Geçerli fatura değildir. VKN'si 1111111111 olan bayi e-Fatura mükellefi sayılır (e-Fatura yolunu denemek için).
 */
class DenemeProvider implements InvoiceProvider
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

    public function send(Invoice $invoice, string $documentType, ?string $alias): array
    {
        $prefix = $documentType === 'e_fatura' ? 'DNF' : 'DNM';
        $year = $invoice->issue_date->format('Y');
        $seq = Invoice::where('invoice_no', 'like', "{$prefix}{$year}%")->count() + 1;
        $invoice->invoice_no = $prefix.$year.str_pad((string) $seq, 9, '0', STR_PAD_LEFT);
        $invoice->ettn = (string) Str::uuid();

        return ['invoice_no' => $invoice->invoice_no, 'ettn' => $invoice->ettn, 'pdf' => $this->pdf->render($invoice, 'DENEME — GEÇERLİ FATURA DEĞİLDİR')];
    }

    public function find(Invoice $invoice): ?array
    {
        return null; // deneme sürücüsünde gönderim hiçbir yere gitmediği için sonucu belirsiz kalan fatura kesilmemiştir
    }

    public function status(Invoice $invoice): ?array
    {
        return ['state' => 'ok', 'status' => 'Deneme', 'message' => null];
    }

    public function cancel(Invoice $invoice, string $reason): void {}

    public function test(): string
    {
        return 'Deneme sürücüsü çalışıyor. Faturalar hiçbir yere gönderilmez; gerçek fatura için Uyumsoft bilgilerini girin.';
    }
}
