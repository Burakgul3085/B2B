<?php

namespace App\Domain\Invoicing;

use App\Domain\Tenancy\TenantContext;
use App\Models\Business;
use App\Models\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

/**
 * B2B faturasının PDF görünümü: taslak önizlemesi ve deneme sürücüsünün belgesi. Uyumsoft'ta resmî PDF'i sağlayıcı
 * üretir; bu görünüm kontrol amaçlıdır ve geçerli fatura olmadığında üzerinde filigran yazar.
 */
final class InvoicePdf
{
    public function __construct(private InvoiceSettings $settings, private TenantContext $tenant) {}

    public function render(Invoice $invoice, string $watermark): string
    {
        $invoice->loadMissing(['lines', 'order']);
        $business = Business::findOrFail($this->tenant->businessId());
        $html = view('business.invoices.pdf', [
            'invoice' => $invoice,
            'totals' => InvoiceMath::totals($invoice->lines->map(fn ($l) => $l->only(['net_amount', 'vat_amount', 'line_total', 'vat_rate']))),
            'seller' => $this->settings->seller(),
            'logo' => $this->logo($business),
            'brand' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $business->accent_color) ? $business->accent_color : '#1e3a8a',
            'watermark' => $watermark,
        ])->render();

        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /** İşletme logosu (Dompdf WEBP desteklemediği için PNG'ye çevrilir). */
    private function logo(Business $business): ?string
    {
        if (! $business->logo_path || ! Storage::disk('local')->exists($business->logo_path)) {
            return null;
        }
        $image = @imagecreatefromstring(Storage::disk('local')->get($business->logo_path));
        if (! $image) {
            return null;
        }
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }
}
