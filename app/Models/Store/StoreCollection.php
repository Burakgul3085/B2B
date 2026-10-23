<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Seçki: yönetimin elle seçtiği ürün listesi ("Okula dönüş", "Ofis masası" …). Ana sayfada kutu, /secki/{slug} sayfası. */
class StoreCollection extends Model
{
    public const MAX_PRODUCTS = 48;

    protected $attributes = ['is_active' => true, 'sort' => 0];

    protected $fillable = ['title', 'slug', 'subtitle', 'color', 'is_active', 'sort'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(StoreProduct::class, 'store_collection_product', 'collection_id', 'product_id')->withPivot('sort')->orderByPivot('sort');
    }
}
