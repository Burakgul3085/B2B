<?php

namespace App\Domain\Catalog;

use App\Domain\Inventory\Quantity;

/** Bayiye gösterilen stok bilgisi; işletme ayarına göre adet veya seviye (K4). */
final class StockLevel
{
    /** @return array{state: 'in'|'low'|'out', label: string, tone: string} */
    public static function for(string $qty, string $critical, string $display, string $unitName): array
    {
        $state = bccomp($qty, '0', 3) <= 0 ? 'out' : (bccomp($qty, $critical, 3) <= 0 ? 'low' : 'in');

        $label = match (true) {
            $state === 'out' => 'Stokta yok',
            $display === 'exact' => Quantity::format($qty).' '.$unitName.' stokta',
            $state === 'low' => 'Sınırlı stok',
            default => 'Stokta',
        };

        return ['state' => $state, 'label' => $label, 'tone' => ['in' => 'success', 'low' => 'warning', 'out' => 'danger'][$state]];
    }
}
