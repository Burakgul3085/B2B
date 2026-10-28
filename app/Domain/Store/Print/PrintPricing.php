<?php

namespace App\Domain\Store\Print;

use App\Domain\Support\Money;
use DomainException;

/**
 * Baskı fiyatı (her zaman sunucuda): basılacak sayfa → bir yüze sığan sayfaya göre yüz sayısı → önlü arkalıysa yaprak.
 *  - Yüz başı fiyat, tüm kopyalarda toplam basılan yüz sayısına göre kademeden seçilir (renk ve tek/çift yüz ayrı).
 *  - A3 seçilirse yüz fiyatı çarpanla çarpılır.
 *  - Ciltleme kopya başınadır: zımba sabit (en fazla N yaprak), spiral yaprak sayısına göre kademeli.
 */
class PrintPricing
{
    public function __construct(private PrintSettings $settings) {}

    /**
     * @return array{pages: int, sides: int, sheets: int, copies: int, side_price: string, print_total: string, binding_unit: string,
     *               binding_total: string, total: string, vat_rate: string, lines: list<array{0: string, 1: string}>}
     *
     * @throws DomainException ciltleme bu yaprak sayısına uygun değilse
     */
    public function quote(int $totalPages, PrintOptions $o): array
    {
        $s = $this->settings->all();
        $pages = $o->selectedPages($totalPages);
        $sides = (int) ceil($pages / $o->layout);
        $sheets = $o->duplex ? (int) ceil($sides / 2) : $sides;
        $allSides = $sides * $o->copies;

        $tier = collect($s['tiers'])->sortBy('from')->filter(fn ($t) => $allSides >= (int) $t['from'])->last() ?? $s['tiers'][0];
        $key = $o->color.($o->duplex ? '_duplex' : '');
        $sidePrice = (string) $tier[$key];
        if ($o->size === 'A3') {
            $sidePrice = bcmul($sidePrice, (string) $s['a3_multiplier'], 2);
        }
        $printTotal = bcmul($sidePrice, (string) $allSides, 2);

        $bindingUnit = match ($o->binding) {
            'staple' => $sheets <= (int) $s['staple_max_sheets'] ? (string) $s['staple']
                : throw new DomainException("Zımba en fazla {$s['staple_max_sheets']} yaprakta yapılabilir; bu belge {$sheets} yaprak. Spiral cilt seçin."),
            'spiral' => (string) ((collect($s['spiral'])->sortBy('to')->first(fn ($t) => $sheets <= (int) $t['to']) ?? [])['price']
                ?? throw new DomainException('Spiral cilt en fazla '.collect($s['spiral'])->max('to')." yaprakta yapılabilir; bu belge {$sheets} yaprak.")),
            default => '0.00',
        };
        $bindingTotal = bcmul($bindingUnit, (string) $o->copies, 2);
        $total = bcadd($printTotal, $bindingTotal, 2);

        $lines = [["{$allSides} yüz × ".Money::format($sidePrice), Money::format($printTotal)]];
        if ($o->binding !== 'none') {
            $lines[] = [PrintOptions::BINDINGS[$o->binding]." × {$o->copies}", Money::format($bindingTotal)];
        }

        return ['pages' => $pages, 'sides' => $sides, 'sheets' => $sheets, 'copies' => $o->copies, 'side_price' => $sidePrice,
            'print_total' => $printTotal, 'binding_unit' => $bindingUnit, 'binding_total' => $bindingTotal, 'total' => $total,
            'vat_rate' => (string) $s['vat_rate'], 'lines' => $lines];
    }
}
