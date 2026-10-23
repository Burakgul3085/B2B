<?php

namespace App\Domain\Store\Storefront;

use App\Models\Store\StoreProductVariant;
use Illuminate\Validation\ValidationException;

/** Yönetimde stok koduyla (SKU) ürün seçimi: seçkiler ve blog yazıları. Sıra korunur, aynı ürünün seçenekleri tek ürün sayılır. */
final class ProductSkus
{
    /**
     * @return list<int> ürün kimlikleri, yazılış sırasıyla
     *
     * @throws ValidationException
     */
    public static function resolve(?string $text, int $max, string $field = 'product_skus'): array
    {
        $skus = array_values(array_unique(array_filter(array_map(fn ($v) => strtoupper(trim($v)), preg_split('/[\s,;]+/', (string) $text)))));
        $bySku = $skus ? StoreProductVariant::whereIn('sku', $skus)->get(['sku', 'product_id'])->keyBy(fn ($v) => strtoupper($v->sku)) : collect();
        if ($unknown = array_diff($skus, $bySku->keys()->all())) {
            throw ValidationException::withMessages([$field => 'Bulunamayan ürün kodu: '.implode(', ', array_slice($unknown, 0, 10)).'.']);
        }
        $ids = array_values(array_unique(array_map(fn ($sku) => $bySku[$sku]->product_id, $skus)));
        if (count($ids) > $max) {
            throw ValidationException::withMessages([$field => "En fazla {$max} ürün seçilebilir."]);
        }

        return $ids;
    }

    /** @param  list<int>  $productIds  Her ürünün ilk seçeneğinin stok kodu, aynı sırayla (formda gösterim). */
    public static function of(array $productIds): string
    {
        $first = StoreProductVariant::whereIn('product_id', $productIds)->orderBy('sort')->orderBy('id')->get(['product_id', 'sku'])->unique('product_id')->keyBy('product_id');

        return implode('
', array_filter(array_map(fn ($id) => $first[$id]->sku ?? null, $productIds)));
    }
}
