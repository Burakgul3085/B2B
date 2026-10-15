<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use BelongsToBusiness;

    /** Formdan ve içe aktarmadan doldurulabilen alanlar. */
    public const EDITABLE = [
        'sku', 'barcode', 'name', 'description', 'category_id', 'brand_id', 'model', 'unit_id', 'pack_quantity',
        'purchase_price', 'list_price', 'dealer_price', 'tax_rate_id', 'price_includes_tax', 'min_order_qty',
        'order_step', 'critical_stock_level', 'variant_group_id', 'variant_values', 'is_active', 'visible_to_dealers',
    ];

    protected $fillable = self::EDITABLE;

    protected $attributes = [
        'is_active' => true, 'visible_to_dealers' => true, 'price_includes_tax' => false,
        'pack_quantity' => '1.000', 'min_order_qty' => '1.000', 'order_step' => '1.000', 'critical_stock_level' => '0.000',
    ];

    protected function casts(): array
    {
        return [
            'pack_quantity' => 'decimal:3',
            'purchase_price' => 'decimal:4',
            'list_price' => 'decimal:4',
            'dealer_price' => 'decimal:4',
            'min_order_qty' => 'decimal:3',
            'order_step' => 'decimal:3',
            'critical_stock_level' => 'decimal:3',
            'price_includes_tax' => 'boolean',
            'is_active' => 'boolean',
            'visible_to_dealers' => 'boolean',
            'variant_values' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    public function scopeNotArchived(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function variantGroup(): BelongsTo
    {
        return $this->belongsTo(VariantGroup::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('is_primary')->orderBy('sort');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->orderByDesc('is_primary')->orderBy('sort');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function variantLabel(): ?string
    {
        return $this->variant_values ? implode(' / ', $this->variant_values) : null;
    }
}
