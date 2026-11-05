<?php

namespace App\Domain\Store\Print;

use App\Domain\Support\Money;
use DomainException;

/**
 * Baskı fiyatı (her zaman sunucuda) yaprak başınadır:
 *  - Kademe, basılacak sayfa sayısına göre seçilir: sayfa aralığı yoksa belgenin tüm sayfaları, varsa aralıktaki sayfalar.
 *    Önlü arkalı, bir yüze çok sayfa ve kopya sayısı kademeyi değiştirmez (siyah-beyaz ve renkli ayrı fiyat).
 *  - Tutar = kademenin yaprak fiyatı × kopya başı yaprak × kopya. Yaprak: sayfa → bir yüze sığan sayfaya göre yüz →
 *    önlü arkalıysa yüz ÷ 2. Kademe sınırında düzeltme yapılmaz (199 sayfa 100–199, 200 sayfa 200–299 kademesindedir).
 *  - Basılacak sayfa özel teklif eşiğine ulaşırsa fiyat verilmez (SpecialQuoteRequired): sepete eklenemez, sipariş verilemez.
 *  - A3 seçilirse yaprak fiyatı çarpanla çarpılır.
 *  - Ciltleme yalnızca spiraldir: tek sabit fiyat, kopya başına (yaprak sınırı yok). Zımba sunulmaz.
 */
class PrintPricing
{
    public function __construct(private PrintSettings $settings) {}

    /**
     * @return array{pages: int, sides: int, sheets: int, copies: int, all_sheets: int, sheet_price: string, print_total: string,
     *               binding_unit: string, binding_total: string, total: string, vat_rate: string, lines: list<array{0: string, 1: string}>}
     *
     * @throws SpecialQuoteRequired basılacak sayfa özel teklif eşiğindeyse
     * @throws DomainException ciltleme artık sunulmuyorsa (eski kayıtta zımba)
     */
    public function quote(int $totalPages, PrintOptions $o): array
    {
        $s = $this->settings->all();
        $pages = $o->selectedPages($totalPages);
        $threshold = $s['quote_threshold'];
        if ($threshold !== null && $pages >= $threshold) {
            throw new SpecialQuoteRequired($pages, $threshold, $s['quote_text']);
        }

        $sides = (int) ceil($pages / $o->layout);
        $sheets = $o->duplex ? (int) ceil($sides / 2) : $sides;
        $allSheets = $sheets * $o->copies;

        $tier = collect($s['tiers'])->sortBy('from')->filter(fn ($t) => $pages >= (int) $t['from'])->last() ?? collect($s['tiers'])->sortBy('from')->first();
        $sheetPrice = $o->size === 'A3' ? bcmul((string) $tier[$o->color], (string) $s['a3_multiplier'], 2) : (string) $tier[$o->color];
        $printTotal = bcmul($sheetPrice, (string) $allSheets, 2);
        $label = "{$sheets} yaprak".($o->copies > 1 ? " × {$o->copies} kopya" : '').' × '.Money::format($sheetPrice)." ({$pages} sayfa kademesi)";
        $lines = [[$label, Money::format($printTotal)]];

        $bindingUnit = match ($o->binding) {
            'none' => '0.00',
            'spiral' => (string) $s['spiral_price'],
            default => throw new DomainException('Bu ciltleme artık sunulmuyor; baskıyı yeniden ekleyin.'),
        };
        $bindingTotal = bcmul($bindingUnit, (string) $o->copies, 2);
        $total = bcadd($printTotal, $bindingTotal, 2);

        if ($o->binding !== 'none') {
            $lines[] = [PrintOptions::BINDINGS[$o->binding]." × {$o->copies}", Money::format($bindingTotal)];
        }

        return ['pages' => $pages, 'sides' => $sides, 'sheets' => $sheets, 'copies' => $o->copies, 'all_sheets' => $allSheets,
            'sheet_price' => $sheetPrice, 'print_total' => $printTotal, 'binding_unit' => $bindingUnit, 'binding_total' => $bindingTotal,
            'total' => $total, 'vat_rate' => (string) $s['vat_rate'], 'lines' => $lines];
    }
}
