<?php

namespace App\Domain\Support;

use RuntimeException;
use ZipArchive;

/**
 * Yazılmış XLSX dosyasının ilk sayfasındaki listeyi Excel tablosu (Ekle > Tablo) yapar; OpenSpout tablo yazamaz.
 *
 * Neden: başlığı sabit tutmak için bölme dondurulunca Excel kaydırırken başlık satırını bir an iki kez çiziyor (Excel'in
 * kendi oluşturduğu dosyada da). Tabloda bölme dondurulmaz; aşağı kaydırınca Excel sütun harflerinin (A, B, C…) yerinde
 * tablonun başlıklarını gösterir. Filtre okları tablonun kendisindedir (sayfaya ayrıca otomatik filtre konmaz).
 * Hücre biçimleri dosyadaki gibi kalır; tablo stili yalnızca görünmez bir çerçevedir.
 */
final class ExcelTable
{
    /**
     * @param  list<string>  $headers  1. satırdaki başlıklar (benzersiz, hücredekiyle aynı)
     * @param  int  $rows  başlık dahil satır sayısı (en az 2)
     */
    public static function add(string $path, string $name, array $headers, int $rows): void
    {
        $ref = 'A1:'.self::column(count($headers)).max(2, $rows);
        $columns = '';
        foreach (array_values($headers) as $i => $header) {
            $columns .= '<tableColumn id="'.($i + 1).'" name="'.self::escape($header).'"/>';
        }
        $table = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
            .'<table xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" id="1" name="'.$name.'" displayName="'.$name.'" ref="'.$ref.'" headerRowCount="1" totalsRowShown="0">'
            .'<autoFilter ref="'.$ref.'"/><tableColumns count="'.count($headers).'">'.$columns.'</tableColumns>'
            .'<tableStyleInfo name="TableStyleLight1" showFirstColumn="0" showLastColumn="0" showRowStripes="0" showColumnStripes="0"/></table>';

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Excel dosyası açılamadı.');
        }

        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $sheet = preg_replace('#<autoFilter [^>]*/>#', '', $sheet, 1);
        $sheet = str_replace('</worksheet>', '<tableParts count="1"><tablePart r:id="rIdTable1"/></tableParts></worksheet>', $sheet);

        $relsPath = 'xl/worksheets/_rels/sheet1.xml.rels';
        $rels = $zip->getFromName($relsPath) ?: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>';
        $rels = str_replace('</Relationships>', '<Relationship Id="rIdTable1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/table" Target="../tables/table1.xml"/></Relationships>', $rels);

        $types = str_replace('</Types>', '<Override ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.table+xml" PartName="/xl/tables/table1.xml"/></Types>',
            (string) $zip->getFromName('[Content_Types].xml'));

        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->addFromString($relsPath, $rels);
        $zip->addFromString('[Content_Types].xml', $types);
        $zip->addFromString('xl/tables/table1.xml', $table);
        $zip->close();
    }

    private static function column(int $number): string
    {
        $letters = '';
        while ($number > 0) {
            $number--;
            $letters = chr(65 + $number % 26).$letters;
            $number = intdiv($number, 26);
        }

        return $letters;
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
