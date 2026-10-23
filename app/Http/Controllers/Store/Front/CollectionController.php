<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Storefront\Catalog;
use App\Domain\Store\Storefront\Palette;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreCollection;
use Illuminate\View\View;

/** Seçki sayfası: /secki/{slug} — yönetimin seçtiği ürünler, seçki sırasıyla. */
class CollectionController extends Controller
{
    public function show(string $slug, Catalog $catalog): View
    {
        $collection = StoreCollection::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $ids = $collection->products()->pluck('store_products.id')->all();
        $products = $catalog->forCards($catalog->products()->whereIn('id', $ids))->get()->sortBy(fn ($p) => array_search($p->id, $ids, true))->values();

        return view('store.front.collection', ['collection' => $collection, 'products' => $products, 'palette' => Palette::classes($collection->color ?: 'orange')]);
    }
}
