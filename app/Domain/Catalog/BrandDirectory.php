<?php

namespace App\Domain\Catalog;

use App\Models\Brand;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

/**
 * Bayinin Markalar sayfası: yalnızca bayinin sipariş verebileceği en az bir ürünü olan markalar.
 * Ürün sayısı, katalogda o marka seçildiğinde görünen ürün sayısıyla aynıdır.
 */
class BrandDirectory
{
    /** Türk alfabesi (ve yabancı markalar için Q, W, X). */
    public const LETTERS = ['A', 'B', 'C', 'Ç', 'D', 'E', 'F', 'G', 'Ğ', 'H', 'I', 'İ', 'J', 'K', 'L', 'M', 'N', 'O', 'Ö', 'P', 'Q', 'R', 'S', 'Ş', 'T', 'U', 'Ü', 'V', 'W', 'X', 'Y', 'Z'];

    /** @return Collection<int, Brand> `product_count` ve `letter` eklenmiş, Türkçe alfabetik sıralı */
    public function all(): Collection
    {
        $brands = Brand::where('is_active', true)
            ->withCount(['products as product_count' => fn ($q) => $q->notArchived()->where('is_active', true)->where('visible_to_dealers', true)])
            ->get()
            ->filter(fn (Brand $b) => $b->product_count > 0)
            ->each(fn (Brand $b) => $b->setAttribute('letter', $this->letter($b->name)));

        $collator = class_exists(\Collator::class) ? new \Collator('tr_TR') : null;

        return $brands->sort(fn (Brand $a, Brand $b) => $collator ? $collator->compare($a->name, $b->name) : strcasecmp($a->name, $b->name))->values();
    }

    /**
     * Öne çıkan markalar: son 90 günde onaylanan siparişlerde en çok alınanlar.
     * Henüz satış yoksa en çok ürünü olan markalar.
     *
     * @param  Collection<int, Brand>  $brands  all() sonucu
     * @return Collection<int, Brand>
     */
    public function featured(Collection $brands, int $limit = 8): Collection
    {
        if ($brands->count() < 2) {
            return collect();
        }

        $sold = OrderItem::query()
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['approved', 'preparing', 'completed'])->where('approved_at', '>=', now()->subDays(90)))
            ->whereNotNull('products.brand_id')
            ->selectRaw('products.brand_id, SUM(order_items.quantity_approved) as qty')
            ->groupBy('products.brand_id')
            ->pluck('qty', 'brand_id');

        $byId = $brands->keyBy('id');
        $ranked = $sold->sortDesc()->keys()->filter(fn ($id) => $byId->has($id))->map(fn ($id) => $byId[$id]);

        // Satışı olmayan markalarla tamamlanır (en çok ürünü olanlar önce).
        $rest = $brands->reject(fn (Brand $b) => $sold->has($b->id))->sortByDesc('product_count');

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
