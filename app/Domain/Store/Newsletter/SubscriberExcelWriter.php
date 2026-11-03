<?php

namespace App\Domain\Store\Newsletter;

use App\Domain\Store\Reports\ReportCell;
use App\Domain\Store\Reports\StoreReportBranding;
use DateTimeImmutable;
use DateTimeZone;
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
 * E-bülten abone listesi ve İYS listesi için düzenli XLSX (akış halinde; satır sayısından bağımsız sabit bellek).
 *
 * Sütun başlıkları 1. satırdadır ve dondurulur: kaydırırken başlık hep üstte kalır. Üstte birleştirilmiş antet satırı
 * bilerek yoktur; birleştirilmiş satır dondurulmuş alana girince Excel kaydırırken başlığı iki kez çiziyor
 * (StoreExcelWriter'daki not). İYS listesinde de bu sayede ilk satır İYS sütun adlarıdır, veri 2. satırdan başlar.
 * Boş hücrelere "—" yazılır: uzun metin yandaki boş hücrenin üzerine taşmaz.
 *
 * @phpstan-type Column array{label: string, width: float, type?: 'text'|'center'|'datetime'|'status'}
 */
final class SubscriberExcelWriter
{
    private const INK = '0F172A';

    private const LINE = 'E2E8F0';

    private const ZEBRA = 'F8FAFC';

    /** Durum hücrelerinin renkleri (yazı, zemin). */
    private const TONES = [
        'success' => ['166534', 'DCFCE7'],
        'warning' => ['92400E', 'FEF3C7'],
        'muted' => ['475569', 'F1F5F9'],
        'danger' => ['991B1B', 'FEE2E2'],
    ];

    /** @var array<string, Style> */
    private array $styles = [];

    /**
     * @param  list<Column>  $columns
     * @param  iterable<list<mixed>>  $rows  hücre değerleri; durum sütununda [metin, ton]
     */
    public function write(string $path, string $title, array $columns, iterable $rows): void
    {
        $company = StoreReportBranding::company();
        $last = count($columns) - 1;

        $options = new Options(
            pageMargin: new PageMargin(top: 0.6, right: 0.4, bottom: 0.7, left: 0.4, header: 0.3, footer: 0.3),
            pageSetup: new PageSetup(PageOrientation::LANDSCAPE, PaperSize::A4, fitToHeight: 0, fitToWidth: 1),
            headerFooter: new HeaderFooter(oddFooter: htmlspecialchars('&L&8'.$this->escapeHf($company).'&C&8'.$this->escapeHf($title).'&R&8Sayfa &P / &N', ENT_XML1)),
            properties: new Properties(title: $title, application: config('app.name'), creator: $company, lastModifiedBy: $company, language: 'tr-TR'),
        );

        $writer = new Writer($options);
        $writer->openToFile($path);
        $sheet = $writer->getCurrentSheet();
        $sheet->setName(mb_substr($title, 0, 31));
        $sheet->setSheetView(new SheetView(showGridLines: false, freezeRow: 2));
        $sheet->setPrintTitleRows('$1:$1');
        foreach ($columns as $i => $column) {
            $sheet->setColumnWidth($column['width'], $i + 1);
        }

        $writer->addRow(new Row(array_map(fn ($c) => Cell::fromValue($c['label'], $this->style('head', $c['type'] ?? 'text')), $columns), 28));

        $written = 0;
        foreach ($rows as $values) {
            $variant = $written % 2 === 1 ? 'zebra' : 'data';
            $cells = [];
            foreach ($columns as $i => $column) {
                $cells[] = $this->cell($column['type'] ?? 'text', $values[$i] ?? null, $variant);
            }
            $writer->addRow(new Row($cells, 20));
            $written++;
        }
        if ($written === 0) {
            $writer->addRow(new Row([Cell::fromValue('Kayıt bulunamadı.', $this->style('empty'))], 22));
        } else {
            $sheet->setAutoFilter(new AutoFilter(0, 1, $last, 1 + $written));
        }

        $writer->close();
    }

    private function cell(string $type, mixed $value, string $variant): Cell
    {
        if ($type === 'status' && is_array($value)) {
            return Cell::fromValue(ReportCell::safeText((string) $value[0]), $this->style('status', $value[1] ?? 'muted'));
        }
        if ($value === null || $value === '') {
            return Cell::fromValue('—', $this->style($variant, 'empty'));
        }
        if ($type === 'datetime' && $value instanceof \DateTimeInterface) {
            // Excel saat dilimi bilmez: yerel saat "duvar saati" olarak yazılır.
            $local = new DateTimeImmutable($value->setTimezone(new DateTimeZone('Europe/Istanbul'))->format('Y-m-d H:i:s'), new DateTimeZone('UTC'));

            return Cell::fromValue($local, $this->style($variant, 'datetime'));
        }

        return Cell::fromValue(ReportCell::safeText((string) $value), $this->style($variant, $type));
    }

    private function style(string $name, string $type = 'text'): Style
    {
        $key = "{$name}|{$type}";
        if (isset($this->styles[$key])) {
            return $this->styles[$key];
        }

        $align = in_array($type, ['center', 'datetime', 'status', 'empty'], true) ? CellAlignment::CENTER : CellAlignment::LEFT;
        $base = (new Style)->withFontName('Calibri')->withFontSize(10)->withFontColor(self::INK)
            ->withCellVerticalAlignment(CellVerticalAlignment::CENTER)->withShouldWrapText(false);
        $bottom = new Border(new BorderPart(BorderName::BOTTOM, self::LINE, BorderWidth::THIN, BorderStyle::SOLID));

        $style = match ($name) {
            'head' => $base->withFontBold(true)->withFontColor('FFFFFF')->withBackgroundColor(StoreReportBranding::NAVY)->withCellAlignment($align)
                ->withBorder(new Border(
                    new BorderPart(BorderName::RIGHT, '33416A', BorderWidth::THIN, BorderStyle::SOLID),
                    new BorderPart(BorderName::BOTTOM, StoreReportBranding::ORANGE, BorderWidth::MEDIUM, BorderStyle::SOLID),
                )),
            'status' => $base->withFontBold(true)->withFontColor(self::TONES[$type][0] ?? self::INK)
                ->withBackgroundColor(self::TONES[$type][1] ?? 'FFFFFF')->withCellAlignment(CellAlignment::CENTER)->withBorder($bottom),
            'empty' => $base->withFontItalic(true)->withFontColor('64748B'),
            default => ($name === 'zebra' ? $base->withBackgroundColor(self::ZEBRA) : $base)->withCellAlignment($align)->withBorder($bottom)
                ->withFontColor($type === 'empty' ? '94A3B8' : self::INK),
        };
        if ($type === 'datetime' && $name !== 'head') {
            $style = $style->withFormat('dd.mm.yyyy hh:mm');
        }

        return $this->styles[$key] = $style;
    }

    private function escapeHf(string $text): string
    {
        return str_replace('&', '&&', $text);
    }
}
