<?php

namespace App\Domain\Support;

use OpenSpout\Writer\Common\Entity\SheetViewInterface;

/**
 * Excel'in kendi kaydettiği biçimde sayfa görünümü (ızgara çizgisi kapalı, seçili sekme, seçim). OpenSpout'un
 * SheetView'i seçim yazmaz ve sayfayı seçili sekme yapmaz. Bölme dondurmak mümkündür ama listelerde kullanılmaz:
 * donmuş başlığı Excel kaydırırken bir an iki kez çiziyor (Excel'in kendi dosyalarında da); başlığın görünmesi için
 * liste Excel tablosu yapılır (ExcelTable).
 */
final readonly class ExcelSheetView implements SheetViewInterface
{
    /**
     * @param  int  $frozenRows  üstte sabit kalacak satır sayısı (0: dondurulmaz)
     * @param  bool  $selected  dosya açılınca görünen sayfa (çok sayfalı dosyada yalnızca biri)
     */
    public function __construct(private int $frozenRows = 0, private bool $selected = true) {}

    public function getXml(): string
    {
        $view = '<sheetView showGridLines="0"'.($this->selected ? ' tabSelected="1"' : '').' workbookViewId="0">';
        if ($this->frozenRows < 1) {
            return $view.'<selection activeCell="A1" sqref="A1"/></sheetView>';
        }

        $first = 'A'.($this->frozenRows + 1);

        return $view.'<pane ySplit="'.$this->frozenRows.'" topLeftCell="'.$first.'" activePane="bottomLeft" state="frozen"/>'
            .'<selection pane="bottomLeft" activeCell="'.$first.'" sqref="'.$first.'"/></sheetView>';
    }
}
