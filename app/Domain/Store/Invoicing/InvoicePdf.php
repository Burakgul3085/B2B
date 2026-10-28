<?php

namespace App\Domain\Store\Invoicing;

use App\Domain\Store\Reports\StoreReportBranding;
use App\Models\Store\StoreEInvoice;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Faturanın PDF görünümü: taslak önizlemesi ve deneme sürücüsünün ürettiği belge. Uyumsoft'ta PDF'i sağlayıcı
 * üretir; bu görünüm yalnızca kontrol amaçlıdır ve üzerinde "geçerli fatura değildir" yazar.
 */
final class InvoicePdf
{
    public function render(StoreEInvoice $invoice, string $watermark): string
    {
        $invoice->loadMissing(['lines', 'order']);
        $html = view('store.admin.invoices.pdf', [
            'invoice' => $invoice,
            'totals' => InvoiceMath::totals($invoice->lines->map(fn ($l) => $l->only(['net_amount', 'vat_amount', 'line_total', 'vat_rate']))),
            'seller' => StoreReportBranding::company(),
            'sellerLines' => StoreReportBranding::contactLines(),
            'logo' => StoreReportBranding::logoDataUri(),
            'brand' => '#'.StoreReportBranding::NAVY,
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
}
