<?php

namespace App\Domain\Support;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderName;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\BorderStyle;
use OpenSpout\Common\Entity\Style\BorderWidth;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Properties;
use OpenSpout\Writer\XLSX\Validation\Rules\CustomValidationRule;
use OpenSpout\Writer\XLSX\Validation\Rules\ListValidationRule;
use OpenSpout\Writer\XLSX\Validation\ValidationDisplay;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Toplu ürün yükleme şablonu (mağaza ve B2B ortak). İçe aktarıcılar yalnızca İLK sayfanın İLK satırını başlık
 * olarak okur; bu yüzden düzen buna göre kurulur:
 *
 *  1. "Ürünler"            başlık satırı (zorunlu sütunlar turuncu, diğerleri lacivert; dondurulmuş), altında
 *                          doldurulmaya hazır çizgili satırlar. Hücre seçilince sütunun açıklaması balonla çıkar;
 *                          Evet/Hayır, KDV gibi sütunlarda açılır liste vardır.
 *  2. "Nasıl doldurulur"   antet, kısa kurallar ve sütun sütun açıklama tablosu (zorunlu mu, ne yazılır, örnek).
 *  3. "Örnekler"           örnek satırlar (içe aktarılmaz; istenirse ilk sayfaya kopyalanır).
 *
 * Başlıklardaki " *" işareti içe aktarmayı etkilemez (başlıklar harf ve rakama indirgenerek eşlenir).
 */
final class ImportTemplate
{
    private const NAVY = '0E1C3D';

    private const ORANGE = 'EA580C';

    private const INK = '0F172A';

    private const MUTED = '64748B';

    private const LINE = 'E2E8F0';

    private const ZEBRA = 'F8FAFC';

    private const SOFT_ORANGE = 'FFF1E8';

    /** Ürünler sayfasında önceden biçimlenmiş boş satır sayısı ve açılır liste/açıklama kapsamı. */
    private const PREPARED_ROWS = 300;

    private const VALIDATED_ROWS = 5001;

    /** @var array<string, Style> */
    private array $styles = [];

    /**
     * @param  array{company: string, title: string, intro: list<string>, notes?: list<string>,
     *               columns: list<array{title: string, required?: bool, width?: int, hint: string, example?: string, options?: list<string>}>,
     *               examples: list<list<string|int|float>>}  $t
     */
    public function write(string $path, array $t): void
    {
        $columns = $t['columns'];
        $last = count($columns) - 1;

        $options = new Options(properties: new Properties(title: $t['title'], creator: $t['company'], lastModifiedBy: $t['company'], category: 'Şablon', language: 'tr-TR'));
        foreach ($columns as $i => $col) {
            // Hücre seçilince açıklama balonu; liste olan sütunlarda açılır liste (Excel sınırı: 255 karakter).
            // OpenSpout bu metinlerdeki çift tırnağı XML'de kaçırmıyor (dosya bozulur); tek tırnağa çevrilir.
            $plain = fn (string $text, int $max) => mb_substr(str_replace('"', "'", $text), 0, $max);
            $display = new ValidationDisplay(allowBlank: true, showInputMessage: true, showErrorMessage: isset($col['options']),
                promptTitle: $plain($col['title'], 32), prompt: $plain($col['hint'], 250),
                errorTitle: 'Geçersiz değer', error: isset($col['options']) ? $plain('Listeden seçin: '.implode(', ', $col['options']), 250) : null);
            // Liste olmayan sütunlarda kural her değeri kabul eder; yalnızca açıklama balonu gösterilir.
            $rule = isset($col['options']) ? new ListValidationRule($col['options']) : new CustomValidationRule('TRUE');
            $options->addValidation($i, 2, $i, self::VALIDATED_ROWS, $rule, $display);
        }
        // Rehber sayfası: antet satırları birleştirilir.
        foreach ([1, 2, 3] as $row) {
            $options->mergeCells(0, $row, 3, $row, 1);
        }

        $writer = new Writer($options);
        $writer->openToFile($path);

        $this->productsSheet($writer, $columns);
        $this->guideSheet($writer, $t, $options);
        $this->examplesSheet($writer, $columns, $t['examples']);

        $writer->close();
    }

    private function productsSheet(Writer $writer, array $columns): void
    {
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Ürünler');
        $sheet->setSheetView(new SheetView(showGridLines: false, tabSelected: true, freezeRow: 2));
        foreach ($columns as $i => $col) {
            $sheet->setColumnWidth($col['width'] ?? max(12, mb_strlen($col['title']) + 6), $i + 1);
        }

        $writer->addRow($this->headerRow($columns));
        // Tamamen boş satırlar dosyaya yazılmaz; ilk hücre boş metin hücresidir (Excel'de boş görünür, içe aktarıcı atlar).
        for ($r = 0; $r < self::PREPARED_ROWS; $r++) {
            $style = $this->style($r % 2 ? 'zebra' : 'cell');
            $writer->addRow(new Row(array_map(fn ($i) => $i === 0 ? new StringCell('', $style) : Cell::fromValue('', $style), array_keys($columns)), 20));
        }
    }

    private function guideSheet(Writer $writer, array $t, Options $options): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Nasıl doldurulur');
        $sheet->setSheetView(new SheetView(showGridLines: false));
        foreach ([24, 14, 70, 26] as $i => $width) {
            $sheet->setColumnWidth($width, $i + 1);
        }

        $writer->addRow(new Row([Cell::fromValue($t['company'], $this->style('company'))], 30));
        $writer->addRow(new Row([Cell::fromValue($t['title'], $this->style('title'))], 22));
        $writer->addRow(new Row(array_map(fn ($i) => Cell::fromValue($i === 0 ? 'Doldurduğunuz ilk sayfayı (Ürünler) .xlsx olarak kaydedip paneldeki "İçe aktar" ekranından yükleyin.' : '', $this->style('meta')), range(0, 3)), 20));
        $writer->addRow(new Row([Cell::fromValue('')], 8));

        $row = 5;
        foreach ($t['intro'] as $line) {
            $options->mergeCells(0, $row, 3, $row, 1);
            $writer->addRow(new Row([Cell::fromValue('•  '.$line, $this->style('bullet'))], 20));
            $row++;
        }
        $writer->addRow(new Row([Cell::fromValue('')], 10));

        $writer->addRow(new Row(array_map(fn ($h) => Cell::fromValue($h, $this->style('head')), ['Sütun', 'Zorunlu mu?', 'Ne yazılır?', 'Örnek']), 26));
        foreach ($t['columns'] as $i => $col) {
            $variant = $i % 2 ? 'zebra' : 'cell';
            $hint = $col['hint'].(isset($col['options']) ? ' Seçenekler: '.implode(', ', $col['options']).'.' : '');
            $writer->addRow(new Row([
                Cell::fromValue($col['title'], $this->style($variant.'_bold')),
                Cell::fromValue(($col['required'] ?? false) ? 'Zorunlu' : 'İsteğe bağlı', $this->style(($col['required'] ?? false) ? 'required' : $variant.'_muted')),
                Cell::fromValue($hint, $this->style($variant.'_wrap')),
                Cell::fromValue((string) ($col['example'] ?? ''), $this->style($variant.'_mono')),
            ], max(20, 15 * (int) ceil(mb_strlen($hint) / 85))));
        }

        if ($t['notes'] ?? []) {
            $writer->addRow(new Row([Cell::fromValue('')], 10));
            // satır: maddeler + boşluk + tablo başlığı + sütunlar + boşluk
            $row += 2 + count($t['columns']) + 1;
            foreach ($t['notes'] as $note) {
                $options->mergeCells(0, $row, 3, $row, 1);
                $row++;
                $writer->addRow(new Row([Cell::fromValue($note, $this->style('note'))], 18));
            }
        }
    }

    private function examplesSheet(Writer $writer, array $columns, array $examples): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Örnekler');
        $sheet->setSheetView(new SheetView(showGridLines: false, freezeRow: 2));
        foreach ($columns as $i => $col) {
            $sheet->setColumnWidth($col['width'] ?? max(12, mb_strlen($col['title']) + 6), $i + 1);
        }

        $writer->addRow($this->headerRow($columns));
        foreach ($examples as $r => $values) {
            $writer->addRow(new Row(array_map(fn ($v) => Cell::fromValue(is_string($v) ? $v : (string) $v, $this->style($r % 2 ? 'zebra' : 'cell')), $values), 20));
        }
        $writer->addRow(new Row([Cell::fromValue('')], 10));
        $writer->addRow(new Row([Cell::fromValue('Bu sayfa yalnızca örnektir ve içe aktarılmaz. İsterseniz satırları "Ürünler" sayfasına kopyalayıp düzenleyin.', $this->style('note'))], 18));
    }

    private function headerRow(array $columns): Row
    {
        return new Row(array_map(fn ($c) => Cell::fromValue($c['title'].(($c['required'] ?? false) ? ' *' : ''), $this->style(($c['required'] ?? false) ? 'head_required' : 'head')), $columns), 40);
    }

    private function style(string $name): Style
    {
        if (isset($this->styles[$name])) {
            return $this->styles[$name];
        }

        $base = (new Style)->withFontName('Calibri')->withFontSize(10)->withFontColor(self::INK)->withCellVerticalAlignment(CellVerticalAlignment::CENTER);
        $line = new Border(new BorderPart(BorderName::BOTTOM, self::LINE, BorderWidth::THIN, BorderStyle::SOLID));
        $white = new Border(new BorderPart(BorderName::RIGHT, 'FFFFFF', BorderWidth::THIN, BorderStyle::SOLID));
        [$variant, $modifier] = array_pad(explode('_', $name, 2), 2, null);
        $row = match ($variant) {
            'zebra' => $base->withBackgroundColor(self::ZEBRA)->withBorder($line),
            'cell' => $base->withBorder($line),
            default => $base,
        };

        $style = match (true) {
            $name === 'company' => $base->withFontSize(16)->withFontBold(true)->withFontColor(self::NAVY),
            $name === 'title' => $base->withFontSize(13)->withFontBold(true),
            $name === 'meta' => $base->withFontSize(9)->withFontColor(self::MUTED)->withCellVerticalAlignment(CellVerticalAlignment::TOP)
                ->withBorder(new Border(new BorderPart(BorderName::BOTTOM, self::ORANGE, BorderWidth::MEDIUM, BorderStyle::SOLID))),
            $name === 'bullet' => $base->withShouldWrapText(true)->withCellVerticalAlignment(CellVerticalAlignment::TOP),
            $name === 'note' => $base->withFontSize(9)->withFontItalic(true)->withFontColor(self::MUTED),
            $name === 'head' => $base->withFontBold(true)->withFontColor('FFFFFF')->withBackgroundColor(self::NAVY)->withShouldWrapText(true)
                ->withCellAlignment(CellAlignment::CENTER)->withBorder($white),
            $name === 'head_required' => $base->withFontBold(true)->withFontColor('FFFFFF')->withBackgroundColor(self::ORANGE)->withShouldWrapText(true)
                ->withCellAlignment(CellAlignment::CENTER)->withBorder($white),
            $name === 'required' => $base->withFontBold(true)->withFontColor(self::ORANGE)->withBackgroundColor(self::SOFT_ORANGE)->withBorder($line),
            $modifier === 'bold' => $row->withFontBold(true),
            $modifier === 'muted' => $row->withFontColor(self::MUTED),
            $modifier === 'wrap' => $row->withShouldWrapText(true),
            $modifier === 'mono' => $row->withFontName('Consolas')->withFontColor('334155'),
            default => $row,
        };

        return $this->styles[$name] = $style;
    }
}
