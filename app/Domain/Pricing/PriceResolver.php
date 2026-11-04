<?php

namespace App\Domain\Pricing;

use App\Domain\Support\Decimal;
use App\Domain\Support\LocalTime;
use App\Models\DealerRelationship;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Bayiye uygulanacak fiyatı hesaplar (docs/03-is-kurallari.md §4). Tüm bayilere aynı fiyat:
 *   1. İndirimli bayi fiyatı (tarih aralığında ve indirimsiz fiyattan düşükse) — indirimsiz fiyat üstü çizili gösterilir.
 *   2. Ürünün standart bayi fiyatı.
 *   3. Ürünün genel liste fiyatı.
 * Bayiye veya bayilere farklı indirim kupon / kampanyayla verilir (PromotionService, sipariş tutarından).
 *
 * Girilen tüm fiyatlar ürünün "KDV dahil" ayarına göre yorumlanır ve net (KDV hariç) fiyata çevrilir.
 */
class PriceResolver
{
    public function resolve(DealerRelationship $relationship, Product $product, ?string $date = null): ResolvedPrice
    {
        return $this->resolveMany($relationship, collect([$product]), $date)->first();
    }

    /**
     * @param  Collection<int, Product>  $products  taxRate ilişkisi yüklenmiş olmalı (yoksa yüklenir)
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

        return $products->map(function (Product $product) use ($date) {
            $taxRate = (string) $product->taxRate->rate;
            $listNet = $this->net((string) $product->list_price, $product, $taxRate);
            $source = $product->dealer_price !== null ? 'dealer_price' : 'list_price';
            $regular = $this->net($product->regularDealerPrice(), $product, $taxRate);

            if ($product->dealerSaleActiveOn($date)) {
                $sale = $this->net((string) $product->dealer_sale_price, $product, $taxRate);

                return new ResolvedPrice($product->id, $listNet, $sale, 'dealer_sale', '0.00', null, $sale, $taxRate, $regular);
            }

            return new ResolvedPrice($product->id, $listNet, $regular, $source, '0.00', null, $regular, $taxRate);
        });
    }

    /** Ürün sorgusunu bugün indirimli bayi fiyatı geçerli ürünlerle sınırlar (bayi "İndirimdeki ürünler" listesi). */
    public function whereDiscounted(Builder $query, ?string $date = null): Builder
    {
        return $query->onDealerSale($date ?? LocalTime::today());
    }

    /** KDV dahil girilmiş fiyatı nete çevirir. */
    private function net(string $price, Product $product, string $taxRate): string
    {
        if (! $product->price_includes_tax || bccomp($taxRate, '0', 2) === 0) {
            return bcadd($price, '0', 4);
        }

        return Decimal::round(bcdiv($price, bcadd('1', bcdiv($taxRate, '100', 6), 6), 10), 4);
    }
}
