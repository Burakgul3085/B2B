<?php

namespace App\Domain\Store\Storefront;

use App\Domain\Store\Support\PerRequest;
use App\Models\Store\StoreCustomer;
use App\Models\Store\StoreFavorite;
use App\Models\Store\StoreProduct;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

/** Favoriler: yalnızca üyeler. Kartlardaki kalp, istek başına tek sorguyla doldurulur. */
class Favorites
{
    public function __construct(private Catalog $catalog) {}

    /** @return array<int, true> oturumdaki müşterinin favori ürün kimlikleri */
    public static function ids(): array
    {
        return PerRequest::remember('favorites.ids', function () {
            $customer = Auth::guard('customer')->user();

            return $customer ? array_fill_keys(StoreFavorite::where('customer_id', $customer->id)->pluck('product_id')->all(), true) : [];
        });
    }

    public static function has(int $productId): bool
    {
        return isset(self::ids()[$productId]);
    }

    /** Ekliyse çıkarır, değilse ekler. Yeni durumu döndürür. */
    public function toggle(StoreCustomer $customer, StoreProduct $product): bool
    {
        $deleted = StoreFavorite::where('customer_id', $customer->id)->where('product_id', $product->id)->delete();
        if ($deleted) {
            return false;
        }
        StoreFavorite::firstOrCreate(['customer_id' => $customer->id, 'product_id' => $product->id]);

        return true;
    }

    public function products(StoreCustomer $customer): LengthAwarePaginator
    {
        return $this->catalog->forCards($this->catalog->products()
            ->join('store_favorites', fn ($j) => $j->on('store_favorites.product_id', '=', 'store_products.id')->where('store_favorites.customer_id', $customer->id))
            ->select('store_products.*')
            ->orderByDesc('store_favorites.id'))
            ->paginate(Catalog::PER_PAGE);
    }
}
