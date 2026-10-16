<?php

namespace App\Domain\Reporting;

use App\Models\Business;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderName;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\BorderStyle;
use OpenSpout\Common\Entity\Style\BorderWidth;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Options\HeaderFooter;
use OpenSpout\Writer\XLSX\Options\PageMargin;
use OpenSpout\Writer\XLSX\Options\PageOrientation;
use OpenSpout\Writer\XLSX\Options\PageSetup;
use OpenSpout\Writer\XLSX\Options\PaperSize;
use OpenSpout\Writer\XLSX\Properties;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Kurumsal görünümlü XLSX raporu (akış halinde; satır sayısından bağımsız sabit bellek).
 *
 *  1  İşletme unvanı (işletme rengi)            ┐
 *  2  Rapor adı                                 │ antet, tüm sütunlar boyunca birleştirilmiş
 *  3  Rapor tarihi · kayıt sayısı · hazırlayan  ┘ altında işletme renginde çizgi
 *  5  Filtreler
 *  7  Sütun başlıkları (işletme rengi zemin, beyaz yazı) — sabitlenmiş, filtreli, her sayfada yazdırılır
 *  8… Veri (zebra satırlar; ₺, miktar, yüzde ve tarih Excel biçimiyle; sayılar gerçek sayı hücresi)
 *     TOPLAM satırı ve açıklama notu
 */
final class ExcelReportWriter
{
    private const HEADER_ROW = 7;

    private const INK = '0F172A';

    private const MUTED = '64748B';

    private const LINE = 'E2E8F0';

    private const ZEBRA = 'F8FAFC';

    private const TOTAL_FILL = 'EEF2F7';

    private string $brand;

    /** @var array<string, Style> */
    private array $styles = [];

    /**
     * @param  array<string, array{label: string, type: string}>  $columns
     * @param  array<string, string>  $totals
     * @param  array<string, string>  $filterSummary
     * @param  iterable<object>  $rows
     * @param  LazyCollection<int, object>  $sample  sütun genişliği tahmini için ilk satırlar
     */
    public function write(string $path, Report $report, Business $business, array $columns, iterable $rows, LazyCollection $sample,
        int $count, array $totals, array $filterSummary): void
    {
        $this->brand = ReportBranding::color($business);
        $keys = array_keys($columns);
        $last = count($columns) - 1;

        $options = new Options(
            pageMargin: new PageMargin(top: 0.6, right: 0.4, bottom: 0.7, left: 0.4, header: 0.3, footer: 0.3),
            pageSetup: new PageSetup(count($columns) > 6 ? PageOrientation::LANDSCAPE : PageOrientation::PORTRAIT, PaperSize::A4, fitToHeight: 0, fitToWidth: 1),
            // OpenSpout alt bilgiyi XML'e olduğu gibi yazar; & kodları dahil XML için kaçırılır.
            headerFooter: new HeaderFooter(oddFooter: htmlspecialchars('&L&8'.$this->escapeHf($business->legal_name).'&C&8'.$this->escapeHf($report->title()).'&R&8Sayfa &P / &N', ENT_XML1)),
            properties: new Properties(title: $report->title(), subject: $report->description(), application: config('app.name'),
                creator: $business->legal_name, lastModifiedBy: $business->legal_name, category: 'Rapor', language: 'tr-TR'),
        );
        if ($last > 0) {
            foreach ([1, 2, 3, 5] as $row) {
                $options->mergeCells($row === 5 ? 1 : 0, $row, $last, $row);
            }
            $options->mergeCells(0, self::HEADER_ROW + max(1, $count) + ($totals ? 1 : 0) + 2, $last, self::HEADER_ROW + max(1, $count) + ($totals ? 1 : 0) + 2);
        }

        $writer = new Writer($options);
        $writer->openToFile($path);
        $sheet = $writer->getCurrentSheet();
        $sheet->setName($this->sheetName($report->title()));
        $sheet->setSheetView((new SheetView(showGridLines: false))->withFreezeRow(self::HEADER_ROW + 1));
        $sheet->setPrintTitleRows('$'.self::HEADER_ROW.':$'.self::HEADER_ROW);
        foreach ($this->widths($columns, $sample) as $i => $width) {
            $sheet->setColumnWidth($width, $i + 1);
        }

        // Antet
        $writer->addRow(new Row([Cell::fromValue(CellFormatter::safeText($business->legal_name), $this->style('company'))], 30));
        $writer->addRow(new Row([Cell::fromValue(CellFormatter::safeText($report->title()), $this->style('title'))], 22));
        $meta = 'Rapor tarihi: '.ReportBranding::now().'     ·     Kayıt sayısı: '.number_format($count, 0, ',', '.').'     ·     Hazırlayan: '.$business->display_name;
        // Antetin altındaki renkli çizgi: birleştirilmiş satırın her hücresine alt kenarlık (yalnızca boş hücreli satırlar yazılmaz).
        $writer->addRow(new Row(array_map(fn ($i) => Cell::fromValue($i === 0 ? CellFormatter::safeText($meta) : '', $this->style('meta')), array_keys($keys)), 20));
        $writer->addRow(new Row([Cell::fromValue('')], 6));

        $filters = $filterSummary
            ? implode('     ·     ', array_map(fn ($l, $v) => "{$l}: {$v}", array_keys($filterSummary), $filterSummary))
            : 'Filtre uygulanmadı (tüm kayıtlar)';
        $filterCells = [Cell::fromValue('Filtreler', $this->style('filter_label')), Cell::fromValue(CellFormatter::safeText($filters), $this->style('filter_value'))];
        $writer->addRow(new Row($last > 0 ? $filterCells : [Cell::fromValue(CellFormatter::safeText('Filtreler: '.$filters), $this->style('filter_value'))], 20));
        $writer->addRow(new Row([Cell::fromValue('')], 8));

        // Sütun başlıkları
        $writer->addRow(new Row(array_map(fn ($c) => Cell::fromValue($c['label'], $this->style('head', $c['type'])), array_values($columns)), 30));

        // Veri
        $written = 0;
        foreach ($rows as $row) {
            $zebra = $written % 2 === 1;
            $cells = [];
            foreach ($columns as $key => $col) {
                $cells[] = $this->cell($col['type'], $row->{$key} ?? null, $zebra ? 'zebra' : 'data');
            }
            $writer->addRow(new Row($cells, 18));
            $written++;
        }
        if ($written === 0) {
            $writer->addRow(new Row([Cell::fromValue('Seçilen filtrelerle kayıt bulunamadı.', $this->style('empty'))], 22));
        }

        // Toplam
        if ($totals) {
            $cells = [];
            foreach ($keys as $i => $key) {
                $cells[] = isset($totals[$key])
                    ? $this->cell($columns[$key]['type'], $totals[$key], 'total')
                    : Cell::fromValue($i === 0 ? 'TOPLAM' : '', $this->style('total_label'));
            }
            $writer->addRow(new Row($cells, 22));
        }

        $writer->addRow(new Row([Cell::fromValue('')], 8));
        $writer->addRow(new Row([Cell::fromValue(ReportBranding::NOTE, $this->style('note'))], 16));

        if ($written > 0) {
            $sheet->setAutoFilter(new AutoFilter(0, self::HEADER_ROW, $last, self::HEADER_ROW + $written));
        }
        $writer->close();
    }

    private function cell(string $type, mixed $value, string $variant): Cell
    {
        if ($value === null || $value === '') {
            return Cell::fromValue('', $this->style($variant, $type));
        }

        return match (true) {
            CellFormatter::isNumeric($type) => Cell::fromValue((float) $value, $this->style($variant, $type, $this->numberFormat($type, (string) $value))),
            $type === 'date' => Cell::fromValue($this->localDate($value, false), $this->style($variant, $type, 'dd.mm.yyyy')),
            $type === 'datetime' => Cell::fromValue($this->localDate($value, true), $this->style($variant, $type, 'dd.mm.yyyy hh:mm')),
            default => Cell::fromValue(CellFormatter::safeText((string) $value), $this->style($variant, $type)),
        };
    }

    /** Tam sayılar ondalıksız, küsuratlılar ondalıklı gösterilir (ör. 60 ve 2,5). */
    private function numberFormat(string $type, string $value): string
    {
        $whole = fmod((float) $value, 1.0) == 0.0;

        return match ($type) {
            'money' => '#,##0.00 "₺";-#,##0.00 "₺"',
            'percent' => $whole ? '"%"0' : '"%"0.00',
            'qty' => $whole ? '#,##0' : '#,##0.000',
            default => '#,##0',
        };
    }

    private function localDate(mixed $value, bool $withTime): DateTimeImmutable
    {
        $date = Carbon::parse($value);
        $date = $withTime ? $date->timezone('Europe/Istanbul') : $date;

        // Excel saat dilimi bilmez: yerel saat "duvar saati" olarak yazılır.
        return new DateTimeImmutable($date->format('Y-m-d H:i:s'), new DateTimeZone('UTC'));
    }

    private function style(string $name, string $type = 'text', ?string $format = null): Style
    {
        $key = "{$name}|{$type}|{$format}";
        if (isset($this->styles[$key])) {
            return $this->styles[$key];
        }

        $numeric = CellFormatter::isNumeric($type);
        $align = $numeric ? CellAlignment::RIGHT : (in_array($type, ['date', 'datetime'], true) ? CellAlignment::CENTER : CellAlignment::LEFT);
        $base = (new Style)->withFontName('Calibri')->withFontSize(10)->withFontColor(self::INK)->withCellVerticalAlignment(CellVerticalAlignment::CENTER);
        $line = fn (string $color, BorderWidth $w = BorderWidth::THIN) => new BorderPart(BorderName::BOTTOM, $color, $w, BorderStyle::SOLID);

        $style = match ($name) {
            'company' => $base->withFontSize(16)->withFontBold(true)->withFontColor($this->brand),
            'title' => $base->withFontSize(13)->withFontBold(true),
            'meta' => $base->withFontSize(9)->withFontColor(self::MUTED)->withCellVerticalAlignment(CellVerticalAlignment::TOP)
                ->withBorder(new Border($line($this->brand, BorderWidth::MEDIUM))),
            'filter_label' => $base->withFontSize(9)->withFontBold(true)->withFontColor(self::MUTED),
            'filter_value' => $base->withFontSize(9)->withFontColor(self::INK)->withShouldWrapText(true),
            'head' => $base->withFontBold(true)->withFontColor('FFFFFF')->withBackgroundColor($this->brand)->withShouldWrapText(true)
                ->withCellAlignment($align)->withBorder(new Border(new BorderPart(BorderName::RIGHT, 'FFFFFF', BorderWidth::THIN, BorderStyle::SOLID))),
            'data', 'zebra' => ($name === 'zebra' ? $base->withBackgroundColor(self::ZEBRA) : $base)
                ->withCellAlignment($align)->withBorder(new Border($line(self::LINE))),
            'total', 'total_label' => $base->withFontBold(true)->withBackgroundColor(self::TOTAL_FILL)->withCellAlignment($name === 'total' ? $align : CellAlignment::LEFT)
                ->withBorder(new Border(new BorderPart(BorderName::TOP, $this->brand, BorderWidth::MEDIUM, BorderStyle::SOLID), $line($this->brand))),
            'empty' => $base->withFontColor(self::MUTED)->withFontItalic(true),
            'note' => $base->withFontSize(8)->withFontItalic(true)->withFontColor(self::MUTED),
        };

        return $this->styles[$key] = $format ? $style->withFormat($format) : $style;
    }

    /** Başlık ve ilk satırlardaki en uzun değere göre sütun genişliği. @return float[] */
    private function widths(array $columns, LazyCollection $sample): array
    {
        $max = array_map(fn ($c) => mb_strlen($c['label']) * 1.1, $columns);
        foreach ($sample as $row) {
            foreach ($columns as $key => $col) {
                $max[$key] = max($max[$key], mb_strlen(CellFormatter::display($col['type'], $row->{$key} ?? null)));
            }
        }

        return array_values(array_map(fn ($len, $col) => (float) min(55, max(match ($col['type']) {
            'money' => 14, 'date' => 12, 'datetime' => 17, 'percent', 'int' => 9, 'qty' => 11, default => 10,
        }, ceil($len) + 4)), $max, $columns));
    }

    private function sheetName(string $title): string
    {
        return mb_substr(trim(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $title)), 0, 31) ?: 'Rapor';
    }

    private function escapeHf(string $text): string
    {
        return str_replace('&', '&&', $text);
    }
}
