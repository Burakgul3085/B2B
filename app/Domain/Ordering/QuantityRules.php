<?php

namespace App\Domain\Ordering;

use App\Domain\Inventory\Quantity;
use App\Models\Product;

/** Bayi sipariş miktarı kuralları: minimum, artış adımı, birimin küsurat izni. */
final class QuantityRules
{
    /** Hata mesajı veya null. */
    public static function violation(Product $product, string $qty): ?string
    {
        $unit = $product->unit->name;

        if (bccomp($qty, '0', 3) <= 0) {
            return 'Miktar sıfırdan büyük olmalıdır.';
        }
        if (! $product->unit->allows_fraction && ! Quantity::isWhole($qty)) {
            return "\"{$unit}\" biriminde küsuratlı miktar girilemez.";
        }
        if (bccomp($qty, (string) $product->min_order_qty, 3) < 0) {
            return 'En az '.Quantity::format($product->min_order_qty)." {$unit} sipariş verilebilir.";
        }
        if (bccomp(bcmod($qty, (string) $product->order_step, 3), '0', 3) !== 0) {
            return 'Miktar '.Quantity::format($product->order_step).' ve katları olmalıdır.';
        }

        return null;
    }
}
