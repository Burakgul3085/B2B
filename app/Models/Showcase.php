<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ana sayfadaki sekmeli ürün grubu: elle seçilen ürünler veya otomatik kaynak. */
class Showcase extends Model
{
    use BelongsToBusiness;

    public const TYPES = [
        'manual' => 'Elle seçilen ürünler',
        'category' => 'Bir kategorinin ürünleri',
        'brand' => 'Bir markanın ürünleri',
        'new' => 'Yeni eklenen ürünler',
        'best_sellers' => 'En çok satanlar',
    ];

    protected $fillable = ['name', 'type', 'source_id', 'product_limit', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShowcaseProduct::class)->orderBy('sort')->orderBy('id');
    }
}
