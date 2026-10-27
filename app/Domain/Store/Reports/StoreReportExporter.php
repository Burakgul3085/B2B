<?php

namespace App\Domain\Store\Reports;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mağaza raporlarının Excel ve PDF çıktısı. Ekrandakiyle aynı hazırlığı (filtre, sıralama, sütunlar) ve aynı toplam
 * hesabını kullanır. Her dosyada yapboz logolu antet, satıcı bilgileri, rapor tarihi ve filtre özeti bulunur.
 *  - Excel: akış halinde yazılır (bellek sabit); sayılar sayı hücresi, metinler formül enjeksiyonuna karşı korunur.
 *  - PDF: Dompdf ile; okunabilirlik ve sunucu kaynakları için en fazla PDF_ROW_LIMIT satır.
 */
class StoreReportExporter
{
    public const PDF_ROW_LIMIT = 2000;

    public int $pdfRowLimit = self::PDF_ROW_LIMIT;

    public function __construct(private StoreReportRunner $runner) {}

    public function excel(StoreReport $report, array $prepared, string $preparedBy): StreamedResponse
    {
        $columns = $this->columns($report, $prepared);
        $totals = $this->runner->totals($report, $prepared);
        $count = $this->runner->count($report, $prepared);
        $summary = $this->runner->summary($report, $prepared);

        return response()->streamDownload(function () use ($report, $prepared, $preparedBy, $columns, $totals, $count, $summary) {
            (new StoreExcelWriter)->write('php://output', $report, $preparedBy, $columns,
                $this->runner->rows($report, $prepared), $this->runner->rows($report, $prepared)->take(300), $count, $totals, $summary);
        }, $this->filename($report, 'xlsx'), ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** @return StreamedResponse|null null: satır sınırı aşıldı */
    public function pdf(StoreReport $report, array $prepared, string $preparedBy): ?StreamedResponse
    {
        $count = $this->runner->count($report, $prepared);
        if ($count > $this->pdfRowLimit) {
            return null;
        }

        $columns = $this->columns($report, $prepared);
        $html = view('store.admin.reports.pdf', [
            'report' => $report,
            'company' => StoreReportBranding::company(),
            'columns' => $columns,
            'rows' => $this->runner->rows($report, $prepared),
            'count' => $count,
            'totals' => $this->runner->totals($report, $prepared),
            'filterSummary' => $this->runner->summary($report, $prepared),
            'logo' => StoreReportBranding::logoDataUri(),
            'brand' => '#'.StoreReportBranding::NAVY,
            'contact' => StoreReportBranding::contactLines(),
            'generatedAt' => StoreReportBranding::now(),
            'preparedBy' => $preparedBy,
        ])->render();

        $options = new Options;
        $options->setIsRemoteEnabled(false); // dış kaynak yüklenmez
        $options->setDefaultFont('DejaVu Sans'); // Türkçe karakterler
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', count($columns) > 6 ? 'landscape' : 'portrait');
        $dompdf->render();
        $this->pageNumbers($dompdf);

        return response()->streamDownload(fn () => print ($dompdf->output()), $this->filename($report, 'pdf'), ['Content-Type' => 'application/pdf']);
    }

    /** Seçili sütunlar, seçim sırasıyla. @return array<string, array> */
    private function columns(StoreReport $report, array $prepared): array
    {
        return array_replace(array_flip($prepared['columns']), array_intersect_key($report->columns(), array_flip($prepared['columns'])));
    }

    private function filename(StoreReport $report, string $extension): string
    {
        return Str::slug($report->title()).'-'.now()->timezone('Europe/Istanbul')->format('Ymd-Hi').'.'.$extension;
    }

    /** Her sayfanın altına "Sayfa X / Y". */
    private function pageNumbers(Dompdf $dompdf): void
    {
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $width = $dompdf->getFontMetrics()->getTextWidth('Sayfa 99 / 99', $font, 5.2);
        $canvas->page_text($canvas->get_width() - 37 - $width, $canvas->get_height() - 37.5, 'Sayfa {PAGE_NUM} / {PAGE_COUNT}', $font, 5.2, [0.39, 0.45, 0.55]);
    }
}
