<?php

namespace App\Domain\Store\Print;

use App\Domain\Support\Money;
use DomainException;

/**
 * Baskı fiyatı (her zaman sunucuda) yaprak başınadır: basılacak sayfa → bir yüze sığan sayfaya göre yüz → önlü arkalıysa
 * yaprak = yüz ÷ 2. Önlü arkalı ya da bir yüze çok sayfa baskı ek ücret almaz, yalnızca yaprak sayısını azaltır.
 *  - Yaprak fiyatı, tüm kopyalarda toplam yaprak sayısına göre kademeden seçilir (siyah-beyaz ve renkli ayrı).
 *  - Kademe eşiğinin hemen altındaki iş, bir üst kademenin başlangıç tutarından pahalı olmaz (ters fiyat koruması).
 *  - A3 seçilirse yaprak fiyatı çarpanla çarpılır.
 *  - Ciltleme kopya başınadır: zımba sabit (en fazla N yaprak), spiral yaprak sayısına göre kademeli.
 */
class PrintPricing
{
    public function __construct(private PrintSettings $settings) {}

    /**
     * @return array{pages: int, sides: int, sheets: int, copies: int, all_sheets: int, sheet_price: string, print_total: string,
     *               binding_unit: string, binding_total: string, total: string, vat_rate: string, lines: list<array{0: string, 1: string}>,
     *               tip: ?string}
     *
     * @throws DomainException ciltleme bu yaprak sayısına uygun değilse
     */
    public function quote(int $totalPages, PrintOptions $o): array
    {
        $quote = $this->calculate($totalPages, $o);
        $quote['tip'] = null;
        if (! $o->duplex) {
            try {
                $duplex = $this->calculate($totalPages, $o->withDuplex());
                $saving = bcsub($quote['total'], $duplex['total'], 2);
                if (bccomp($saving, '0', 2) > 0) {
                    $quote['tip'] = "Önlü arkalı seçersen {$duplex['all_sheets']} yaprak olur, ".Money::format($saving).' daha az ödersin.';
                }
            } catch (DomainException) {
                // ipucu gösterilmez
            }
        }

        return $quote;
    }

    private function calculate(int $totalPages, PrintOptions $o): array
    {
        $s = $this->settings->all();
        $pages = $o->selectedPages($totalPages);
        $sides = (int) ceil($pages / $o->layout);
        $sheets = $o->duplex ? (int) ceil($sides / 2) : $sides;
        $allSheets = $sheets * $o->copies;

        $tiers = collect($s['tiers'])->sortBy('from')->values();
        $price = fn (array $tier) => $o->size === 'A3' ? bcmul((string) $tier[$o->color], (string) $s['a3_multiplier'], 2) : (string) $tier[$o->color];
        $tier = $tiers->filter(fn ($t) => $allSheets >= (int) $t['from'])->last() ?? $tiers->first();
        $sheetPrice = $price($tier);
        $printTotal = bcmul($sheetPrice, (string) $allSheets, 2);
        $lines = [["{$allSheets} yaprak × ".Money::format($sheetPrice), Money::format($printTotal)]];

        // Üst kademelerin başlangıç tutarı bu işin tutarından düşükse o tutar alınır.
        foreach ($tiers->filter(fn ($t) => (int) $t['from'] > $allSheets) as $upper) {
            $cap = bcmul($price($upper), (string) $upper['from'], 2);
            if (bccomp($cap, $printTotal, 2) < 0) {
                $printTotal = $cap;
                $lines = [["{$allSheets} yaprak (üst kademe tutarı: {$upper['from']} yaprak × ".Money::format($price($upper)).')', Money::format($printTotal)]];
            }
        }

        $bindingUnit = match ($o->binding) {
            'staple' => $sheets <= (int) $s['staple_max_sheets'] ? (string) $s['staple']
                : throw new DomainException("Zımba en fazla {$s['staple_max_sheets']} yaprakta yapılabilir; bu belge {$sheets} yaprak. Spiral cilt seçin."),
            'spiral' => (string) ((collect($s['spiral'])->sortBy('to')->first(fn ($t) => $sheets <= (int) $t['to']) ?? [])['price']
                ?? throw new DomainException('Spiral cilt en fazla '.collect($s['spiral'])->max('to')." yaprakta yapılabilir; bu belge {$sheets} yaprak.")),
            default => '0.00',
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
