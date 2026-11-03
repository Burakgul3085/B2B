<?php

namespace App\Domain\Support;

use OpenSpout\Writer\Common\Entity\SheetViewInterface;

/**
 * Excel'in kendi kaydettiği biçimde sayfa görünümü (ızgara çizgisi kapalı). OpenSpout'un SheetView'i donmuş bölmede
 * seçim (<selection>) yazmaz ve sayfayı seçili sekme yapmaz; Excel bu durumda kaydırırken donmuş başlık satırını bir
 * an iki kez çiziyor. Burada bölme, seçim alt bölmede ilk veri hücresinde olacak şekilde Excel'deki gibi yazılır.
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
