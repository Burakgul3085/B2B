<?php

namespace App\Domain\Store\Storefront;

use App\Domain\Store\Reports\StoreReports;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreOrderItem;
use Illuminate\Support\Collection;

/**
 * Vitrinin Markalar sayfası: yayında en az bir ürünü olan görünür markalar, Türkçe alfabetik ve harf gruplarıyla.
 * Ürün sayısı, Tüm Ürünler'de o marka seçildiğinde görünen ürün sayısıyla aynıdır.
 */
class BrandDirectory
{
    /** Türk alfabesi (ve yabancı markalar için Q, W, X). */
    public const LETTERS = ['A', 'B', 'C', 'Ç', 'D', 'E', 'F', 'G', 'Ğ', 'H', 'I', 'İ', 'J', 'K', 'L', 'M', 'N', 'O', 'Ö', 'P', 'Q', 'R', 'S', 'Ş', 'T', 'U', 'Ü', 'V', 'W', 'X', 'Y', 'Z'];

    /** @return Collection<int, StoreBrand> `products_count` ve `letter` eklenmiş, Türkçe alfabetik sıralı */
    public function all(): Collection
    {
        $brands = StoreBrand::where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->where('is_published', true)->whereHas('activeVariants')])
            ->get()
            ->filter(fn (StoreBrand $b) => $b->products_count > 0)
            ->each(fn (StoreBrand $b) => $b->setAttribute('letter', $this->letter($b->name)));

        $collator = class_exists(\Collator::class) ? new \Collator('tr_TR') : null;

        return $brands->sort(fn (StoreBrand $a, StoreBrand $b) => $collator ? $collator->compare($a->name, $b->name) : strcasecmp($a->name, $b->name))->values();
    }

    /**
     * Öne çıkan markalar: son 90 günde satılan adede göre; satışı olmayanlarla (en çok ürünü olanlar önce) tamamlanır.
     *
     * @param  Collection<int, StoreBrand>  $brands  all() sonucu
     * @return Collection<int, StoreBrand>
     */
    public function featured(Collection $brands, int $limit = 8): Collection
    {
        if ($brands->count() < 2) {
            return collect();
        }

        $sold = StoreOrderItem::query()
            ->join('store_product_variants', 'store_product_variants.id', '=', 'store_order_items.variant_id')
            ->join('store_products', 'store_products.id', '=', 'store_product_variants.product_id')
            ->whereHas('order', fn ($q) => $q->whereIn('status', StoreReports::SALE_STATUSES)->where('created_at', '>=', now()->subDays(90)))
            ->whereNotNull('store_products.brand_id')
            ->selectRaw('store_products.brand_id, SUM(store_order_items.quantity) as qty')
            ->groupBy('store_products.brand_id')
            ->pluck('qty', 'brand_id');

        $byId = $brands->keyBy('id');
        $ranked = $sold->sortDesc()->keys()->filter(fn ($id) => $byId->has($id))->map(fn ($id) => $byId[$id]);
        $rest = $brands->reject(fn (StoreBrand $b) => $sold->has($b->id))->sortByDesc('products_count');

        return $ranked->concat($rest)->take($limit)->values();
    }

    /** Markanın harf grubu: Türkçe büyük harf; harfle başlamıyorsa "#". */
    public function letter(string $name): string
    {
        $first = mb_substr(trim($name), 0, 1, 'UTF-8');
        $upper = mb_strtoupper(strtr($first, ['i' => 'İ', 'ı' => 'I']), 'UTF-8');

        return in_array($upper, self::LETTERS, true) ? $upper : '#';
    }
}
