<?php

namespace App\Domain\Reporting;

use App\Models\Business;
use App\Models\DealerRelationship;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Excel ve PDF dışa aktarma. Ekrandakiyle aynı ReportRunner hazırlığını (filtre, sıralama, sütunlar) ve
 * aynı toplam hesabını kullanır. Her dosyada kurumsal başlık, rapor tarihi ve filtre özeti bulunur.
 *  - Excel: akış halinde yazılır (bellek sabit); sayılar sayı hücresi, metinler formül enjeksiyonuna karşı korunur.
 *  - PDF: Dompdf ile; okunabilirlik ve sunucu kaynakları için en fazla PDF_ROW_LIMIT satır.
 */
class ReportExporter
{
    public const PDF_ROW_LIMIT = 2000;

    /** Uygulanan PDF satır sınırı (testlerde düşürülebilir). */
    public int $pdfRowLimit = self::PDF_ROW_LIMIT;

    public function __construct(private ReportRunner $runner) {}

    public function excel(Report $report, array $prepared, ReportContext $context, Business $business, array $filterSummary): StreamedResponse
    {
        $columns = $this->columns($report, $prepared);
        $totals = $this->runner->totals($report, $prepared, $context);
        $count = $this->runner->count($report, $prepared, $context);
        $filename = Str::slug($report->title()).'-'.now()->format('Ymd-Hi').'.xlsx';

        return response()->streamDownload(function () use ($report, $prepared, $context, $business, $filterSummary, $columns, $totals, $count) {
            (new ExcelReportWriter)->write('php://output', $report, $business, $columns,
                $this->runner->rows($report, $prepared, $context), $this->runner->rows($report, $prepared, $context)->take(300),
                $count, $totals, $filterSummary);
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** @return StreamedResponse|null null: satır sınırı aşıldı */
    public function pdf(Report $report, array $prepared, ReportContext $context, Business $business, array $filterSummary): ?StreamedResponse
    {
        $count = $this->runner->count($report, $prepared, $context);
        if ($count > $this->pdfRowLimit) {
            return null;
        }

        $columns = $this->columns($report, $prepared);
        $html = view('reports.pdf', [
            'report' => $report,
            'business' => $business,
            'columns' => $columns,
            'rows' => $this->runner->rows($report, $prepared, $context),
            'count' => $count,
            'totals' => $this->runner->totals($report, $prepared, $context),
            'filterSummary' => $filterSummary,
            'logo' => $this->logoDataUri($business),
            'brand' => '#'.ReportBranding::color($business),
            'contact' => ReportBranding::contactLines($business),
            'generatedAt' => ReportBranding::now(),
            'landscape' => count($columns) > 6,
            'dealerName' => $context->isDealer() ? DealerRelationship::with('dealer:id,legal_name')->find($context->relationshipId)?->dealer->legal_name : null,
        ])->render();

        $options = new Options;
        $options->setIsRemoteEnabled(false); // dış kaynak yüklenmez
        $options->setDefaultFont('DejaVu Sans'); // Türkçe karakterler
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', count($columns) > 6 ? 'landscape' : 'portrait');
        $dompdf->render();
        $this->pageNumbers($dompdf);

        $filename = Str::slug($report->title()).'-'.now()->format('Ymd-Hi').'.pdf';

        return response()->streamDownload(fn () => print ($dompdf->output()), $filename, ['Content-Type' => 'application/pdf']);
    }

    /** Seçili sütunlar, seçim sırasıyla. @return array<string, array> */
    private function columns(Report $report, array $prepared): array
    {
        return array_replace(array_flip($prepared['columns']), array_intersect_key($report->columns(), array_flip($prepared['columns'])));
    }

    /** Her sayfanın altına "Sayfa X / Y" (toplam sayfa sayısı ancak çizimden sonra bilinir). */
    private function pageNumbers(Dompdf $dompdf): void
    {
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $text = 'Sayfa {PAGE_NUM} / {PAGE_COUNT}';
        $width = $dompdf->getFontMetrics()->getTextWidth('Sayfa 99 / 99', $font, 5.2);
        $canvas->page_text($canvas->get_width() - 37 - $width, $canvas->get_height() - 37.5, $text, $font, 5.2, [0.39, 0.45, 0.55]);
    }

    private function logoDataUri(Business $business): ?string
    {
        if (! $business->logo_path || ! Storage::disk('local')->exists($business->logo_path)) {
            return null;
        }
        // Dompdf WEBP desteklemediği için logo PNG'ye çevrilir.
        $image = @imagecreatefromstring(Storage::disk('local')->get($business->logo_path));
        if (! $image) {
            return null;
        }
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }
}
