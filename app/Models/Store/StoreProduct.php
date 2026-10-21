<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Mağaza ürünü (ürün grubu). Satılabilir birimler varyantlardır; tek seçenekli ürünün tek varyantı vardır.
 * Yayında değilse mağazada görünmez.
 */
class StoreProduct extends Model
{
    protected $attributes = ['is_published' => false, 'is_featured' => false, 'variant_name' => null, 'published_at' => null];

    protected $fillable = ['name', 'slug', 'category_id', 'brand_id', 'variant_name', 'description', 'seo_title', 'seo_description', 'is_published', 'is_featured'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'is_featured' => 'boolean', 'published_at' => 'datetime'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(StoreCategory::class, 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(StoreBrand::class, 'brand_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(StoreProductVariant::class, 'product_id')->orderBy('sort')->orderBy('id');
    }

    /** Mağazada satılan (etkin) varyantlar. */
    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    /** En ucuz etkin varyant (vitrin kartındaki fiyat). activeVariants yüklü olmalıdır. */
    public function cheapestVariant(): ?StoreProductVariant
    {
        return $this->activeVariants->sortBy(fn ($v) => [(float) $v->price, $v->sort, $v->id])->first();
    }

    public function inStock(): bool
    {
        return $this->activeVariants->contains(fn ($v) => $v->stock_quantity > 0);
    }

    public function hasPriceRange(): bool
    {
        return $this->activeVariants->pluck('price')->unique()->count() > 1;
    }

    public function images(): HasMany
    {
        return $this->hasMany(StoreProductImage::class, 'product_id')->orderBy('sort')->orderBy('id');
    }

    /** İlk sıradaki görsel ana görseldir. */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(StoreProductImage::class, 'product_id')->ofMany(['sort' => 'min', 'id' => 'min']);
    }

    public function hasOptions(): bool
    {
        return $this->variant_name !== null;
    }
}
