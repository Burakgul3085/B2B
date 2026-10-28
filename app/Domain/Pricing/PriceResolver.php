<?php

namespace App\Domain\Pricing;

use App\Domain\Support\Decimal;
use App\Domain\Support\LocalTime;
use App\Models\Category;
use App\Models\DealerProductPrice;
use App\Models\DealerRelationship;
use App\Models\DiscountRule;
use App\Models\PriceListItem;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Bayiye uygulanacak fiyatı hesaplar (docs/03-is-kurallari.md §4).
 *
 * Taban fiyat (ilk bulunan):
 *   1. Bayiye özel ürün fiyatı (tarih aralığında) → nihai fiyattır, indirim uygulanmaz.
 *   2. Bayi grubunun fiyat listesindeki fiyat (liste aktif ve tarih aralığında).
 *   3. Ürünün standart bayi fiyatı.
 *   4. Ürünün genel liste fiyatı.
 * İndirim (yalnızca 2–4 için, yığılmaz, tek kural):
 *   önce ürün bazlı, yoksa kategori bazlı (alt kategori, üst kategoriden önce);
 *   aynı seviyede bayiye özel > gruba > tüm bayilere; yine eşitse en son başlayan kural.
 *
 * Girilen tüm fiyatlar ürünün "KDV dahil" ayarına göre yorumlanır ve net (KDV hariç) fiyata çevrilir.
 */
class PriceResolver
{
    private const TARGET_WEIGHT = ['dealer' => 3, 'group' => 2, 'all' => 1];

    public function resolve(DealerRelationship $relationship, Product $product, ?string $date = null): ResolvedPrice
    {
        return $this->resolveMany($relationship, collect([$product]), $date)->first();
    }

    /**
     * @param  Collection<int, Product>  $products  unit ve taxRate ilişkileri yüklenmiş olmalı (yoksa yüklenir)
     * @return Collection<int, ResolvedPrice> ürün ID'sine göre anahtarlı
     */
    public function resolveMany(DealerRelationship $relationship, Collection $products, ?string $date = null): Collection
    {
        if ($products->isEmpty()) {
            return collect();
        }

        $date ??= LocalTime::today();
        $products = $products->keyBy('id');
        $products->each->loadMissing('taxRate');
        $ids = $products->keys()->all();

        $special = $this->dealerSpecialPrices($relationship, $ids, $date);
        $listItems = $this->groupListPrices($relationship, $ids, $date);
        $rules = $this->applicableRules($relationship, $products, $date);
        $parents = Category::whereIn('id', $products->pluck('category_id')->filter()->unique())->pluck('parent_id', 'id');

        return $products->map(function (Product $product) use ($special, $listItems, $rules, $parents) {
            $taxRate = (string) $product->taxRate->rate;
            $listNet = $this->net((string) $product->list_price, $product, $taxRate);

            if ($special->has($product->id)) {
                $base = $this->net((string) $special[$product->id]->price, $product, $taxRate);

                return new ResolvedPrice($product->id, $listNet, $base, 'dealer_special', '0.00', null, $base, $taxRate);
            }

            [$raw, $source] = match (true) {
                $listItems->has($product->id) => [(string) $listItems[$product->id], 'price_list'],
                $product->dealer_price !== null => [(string) $product->dealer_price, 'dealer_price'],
                default => [(string) $product->list_price, 'list_price'],
            };
            $base = $this->net($raw, $product, $taxRate);

            $rule = $this->pickRule($rules, $product, $parents[$product->category_id] ?? null);
            $percent = $rule ? bcadd((string) $rule->percent, '0', 2) : '0.00';
            $unit = $rule
                ? Decimal::round(bcmul($base, bcsub('1', bcdiv($percent, '100', 6), 6), 10), 4)
                : $base;

            return new ResolvedPrice($product->id, $listNet, $base, $source, $percent, $rule?->name, $unit, $taxRate);
        });
    }

    /** KDV dahil girilmiş fiyatı nete çevirir. */
    private function net(string $price, Product $product, string $taxRate): string
    {
        if (! $product->price_includes_tax || bccomp($taxRate, '0', 2) === 0) {
            return bcadd($price, '0', 4);
        }

        return Decimal::round(bcdiv($price, bcadd('1', bcdiv($taxRate, '100', 6), 6), 10), 4);
    }

    /** @return Collection<int, DealerProductPrice> ürün ID'sine göre, aynı ürün için en son başlayan */
    private function dealerSpecialPrices(DealerRelationship $relationship, array $ids, string $date): Collection
    {
        return DealerProductPrice::where('dealer_relationship_id', $relationship->id)
            ->whereIn('product_id', $ids)
            ->where(fn ($q) => $q->whereNull('starts_on')->orWhere('starts_on', '<=', $date))
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $date))
            ->orderByRaw('starts_on IS NULL, starts_on DESC')->orderByDesc('id')
            ->get()
            ->unique('product_id')
            ->keyBy('product_id');
    }

    /** @return Collection<int, string> ürün ID'si → fiyat */
    private function groupListPrices(DealerRelationship $relationship, array $ids, string $date): Collection
    {
        $priceList = $relationship->group?->priceList;

        if (! $priceList || ! $priceList->isCurrent($date)) {
            return collect();
        }

        return PriceListItem::where('price_list_id', $priceList->id)->whereIn('product_id', $ids)->pluck('price', 'product_id');
    }

    private function applicableRules(DealerRelationship $relationship, Collection $products, string $date): Collection
    {
        $categoryIds = $products->pluck('category_id')->filter()->unique();
        $parentIds = Category::whereIn('id', $categoryIds)->whereNotNull('parent_id')->pluck('parent_id');

        return DiscountRule::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_on')->orWhere('starts_on', '<=', $date))
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $date))
            ->where(fn ($q) => $q
                ->where('target', 'all')
                ->orWhere(fn ($q) => $q->where('target', 'group')->where('dealer_group_id', $relationship->dealer_group_id))
                ->orWhere(fn ($q) => $q->where('target', 'dealer')->where('dealer_relationship_id', $relationship->id)))
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('scope', 'product')->whereIn('product_id', $products->keys()))
                ->orWhere(fn ($q) => $q->where('scope', 'category')->whereIn('category_id', $categoryIds->merge($parentIds)->unique())))
            ->get();
    }

    private function pickRule(Collection $rules, Product $product, ?int $parentCategoryId): ?DiscountRule
    {
        $candidates = [
            $rules->where('scope', 'product')->where('product_id', $product->id),
            $product->category_id ? $rules->where('scope', 'category')->where('category_id', $product->category_id) : collect(),
            $parentCategoryId ? $rules->where('scope', 'category')->where('category_id', $parentCategoryId) : collect(),
        ];

        foreach ($candidates as $level) {
            if ($level->isNotEmpty()) {
                return $level->sort(function (DiscountRule $a, DiscountRule $b) {
                    return [self::TARGET_WEIGHT[$b->target], $b->starts_on?->toDateString() ?? '', $b->id]
                        <=> [self::TARGET_WEIGHT[$a->target], $a->starts_on?->toDateString() ?? '', $a->id];
                })->first();
            }
        }

        return null;
    }
}
