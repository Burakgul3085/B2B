<?php

namespace App\Domain\Reporting;

use App\Models\Business;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
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
        $columns = array_intersect_key($report->columns(), array_flip($prepared['columns']));
        $columns = array_replace(array_flip($prepared['columns']), $columns);
        $totals = $this->runner->totals($report, $prepared, $context);
        $filename = Str::slug($report->title()).'-'.now()->format('Ymd-Hi').'.xlsx';

        return response()->streamDownload(function () use ($report, $prepared, $context, $business, $filterSummary, $columns, $totals) {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $bold = (new Style)->withFontBold(true);

            $writer->addRow(Row::fromValuesWithStyle([CellFormatter::safeText($business->legal_name)], $bold));
            $writer->addRow(Row::fromValuesWithStyle([CellFormatter::safeText($report->title())], $bold));
            $writer->addRow(Row::fromValues(['Rapor tarihi: '.now()->timezone('Europe/Istanbul')->format('d.m.Y H:i')]));
            foreach ($filterSummary as $label => $value) {
                $writer->addRow(Row::fromValues([CellFormatter::safeText($label.': '.$value)]));
            }
            $writer->addRow(Row::fromValues(['']));
            $writer->addRow(Row::fromValuesWithStyle(array_column($columns, 'label'), $bold));

            foreach ($this->runner->rows($report, $prepared, $context) as $row) {
                $cells = [];
                foreach ($columns as $key => $col) {
                    $cells[] = $this->cell($col['type'], $row->{$key} ?? null);
                }
                $writer->addRow(new Row($cells));
            }

            if ($totals) {
                $cells = [];
                $first = true;
                foreach ($columns as $key => $col) {
                    $cells[] = isset($totals[$key]) ? Cell::fromValue((float) $totals[$key], $bold) : Cell::fromValue($first ? 'TOPLAM' : '', $bold);
                    $first = false;
                }
                $writer->addRow(new Row($cells));
            }

            $writer->close();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** @return StreamedResponse|null null: satır sınırı aşıldı */
    public function pdf(Report $report, array $prepared, ReportContext $context, Business $business, array $filterSummary): ?StreamedResponse
    {
        if ($this->runner->count($report, $prepared, $context) > $this->pdfRowLimit) {
            return null;
        }

        $columns = array_replace(array_flip($prepared['columns']), array_intersect_key($report->columns(), array_flip($prepared['columns'])));
        $html = view('reports.pdf', [
            'report' => $report,
            'business' => $business,
            'columns' => $columns,
            'rows' => $this->runner->rows($report, $prepared, $context),
            'totals' => $this->runner->totals($report, $prepared, $context),
            'filterSummary' => $filterSummary,
            'logo' => $this->logoDataUri($business),
        ])->render();

        $options = new Options;
        $options->setIsRemoteEnabled(false); // dış kaynak yüklenmez
        $options->setDefaultFont('DejaVu Sans'); // Türkçe karakterler
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', count($columns) > 6 ? 'landscape' : 'portrait');
        $dompdf->render();

        $filename = Str::slug($report->title()).'-'.now()->format('Ymd-Hi').'.pdf';

        return response()->streamDownload(fn () => print ($dompdf->output()), $filename, ['Content-Type' => 'application/pdf']);
    }

    private function cell(string $type, mixed $value): Cell
    {
        if ($value === null || $value === '') {
            return Cell::fromValue('');
        }
        if (CellFormatter::isNumeric($type)) {
            return Cell::fromValue((float) $value);
        }
        if (in_array($type, ['date', 'datetime'], true)) {
            return Cell::fromValue(CellFormatter::display($type, $value));
        }

        return Cell::fromValue(CellFormatter::safeText((string) $value));
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
