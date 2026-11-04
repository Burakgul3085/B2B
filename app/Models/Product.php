<?php

namespace App\Models;

use App\Domain\Inventory\Quantity;
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
        'sku', 'barcode', 'name', 'description', 'category_id', 'brand_id', 'model', 'unit_id', 'sale_mode', 'pack_quantity',
        'purchase_price', 'list_price', 'dealer_price', 'dealer_sale_price', 'dealer_sale_starts_on', 'dealer_sale_ends_on',
        'tax_rate_id', 'price_includes_tax', 'min_order_qty',
        'order_step', 'critical_stock_level', 'option_name', 'option_value', 'is_active', 'visible_to_dealers',
    ];

    protected $fillable = self::EDITABLE;

    /** Satış şekli: miktar ürünün biriminden (yeni ürünlerde adet); koli yalnızca sipariş kuralıdır. */
    public const SALE_MODES = ['single' => 'Tek tek', 'case' => 'Koli halinde', 'custom' => 'Özel kural'];

    protected $attributes = [
        'sale_mode' => 'single', 'is_active' => true, 'visible_to_dealers' => true, 'price_includes_tax' => false,
        'pack_quantity' => '1.000', 'min_order_qty' => '1.000', 'order_step' => '1.000', 'critical_stock_level' => '0.000',
    ];

    protected function casts(): array
    {
        return [
            'pack_quantity' => 'decimal:3',
            'purchase_price' => 'decimal:4',
            'list_price' => 'decimal:4',
            'dealer_price' => 'decimal:4',
            'dealer_sale_price' => 'decimal:4',
            'dealer_sale_starts_on' => 'date',
            'dealer_sale_ends_on' => 'date',
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

    /** Koli halinde satışta en az kaç koli (min_order_qty / koli içi). */
    public function minCases(): int
    {
        return $this->sale_mode === 'case' && bccomp((string) $this->pack_quantity, '0', 3) > 0
            ? (int) bcdiv((string) $this->min_order_qty, (string) $this->pack_quantity, 0)
            : 1;
    }

    /** Bayiye gösterilen sipariş kuralı, ör. "Koli: 24 adet · en az 1 koli" veya "En az 15, 15'er artar". */
    public function saleRuleLabel(): string
    {
        $unit = mb_strtolower($this->unit?->name ?? 'adet', 'UTF-8');
        $min = Quantity::format($this->min_order_qty);
        $step = Quantity::format($this->order_step);

        return match ($this->sale_mode) {
            'case' => 'Koli: '.Quantity::format($this->pack_quantity)." {$unit} · en az {$this->minCases()} koli",
            'custom' => "En az {$min} {$unit}, {$step}'er artar",
            default => bccomp((string) $this->min_order_qty, '1', 3) > 0 ? "Tek tek · en az {$min} {$unit}" : 'Tek tek satılır',
        };
    }

    /** Koli halinde satışta miktarın koli karşılığı, ör. "2 koli"; tam koli değilse veya şekil koli değilse null. */
    public function casesLabel(mixed $quantity): ?string
    {
        if ($this->sale_mode !== 'case' || bccomp((string) $this->pack_quantity, '1', 3) <= 0) {
            return null;
        }
        $qty = (string) $quantity;

        return bccomp(bcmod($qty, (string) $this->pack_quantity, 3), '0', 3) === 0
            ? bcdiv($qty, (string) $this->pack_quantity, 0).' koli'
            : null;
    }

    /** İndirimsiz bayi fiyatı (girildiği gibi): standart bayi fiyatı, yoksa liste fiyatı. */
    public function regularDealerPrice(): string
    {
        return (string) ($this->dealer_price ?? $this->list_price);
    }

    /** İndirimli bayi fiyatı bu tarihte (Y-m-d) geçerli mi? İndirimsiz fiyattan düşük değilse uygulanmaz. */
    public function dealerSaleActiveOn(string $date): bool
    {
        return $this->dealer_sale_price !== null
            && ($this->dealer_sale_starts_on === null || $this->dealer_sale_starts_on->toDateString() <= $date)
            && ($this->dealer_sale_ends_on === null || $this->dealer_sale_ends_on->toDateString() >= $date)
            && bccomp((string) $this->dealer_sale_price, $this->regularDealerPrice(), 4) < 0;
    }

    /** Bu tarihte indirimli bayi fiyatı geçerli ürünler (dealerSaleActiveOn ile aynı koşul). */
    public function scopeOnDealerSale(Builder $query, string $date): void
    {
        $query->whereNotNull('products.dealer_sale_price')
            ->where(fn ($q) => $q->whereNull('products.dealer_sale_starts_on')->orWhere('products.dealer_sale_starts_on', '<=', $date))
            ->where(fn ($q) => $q->whereNull('products.dealer_sale_ends_on')->orWhere('products.dealer_sale_ends_on', '>=', $date))
            ->whereRaw('products.dealer_sale_price < COALESCE(products.dealer_price, products.list_price)');
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

    /** Seçenek değeri, ör. "Kırmızı" (docs/03 §2.2). */
    public function variantLabel(): ?string
    {
        return filled($this->option_value) ? $this->option_value : null;
    }

    /** Aynı ürünün seçenekleri (kendisi dahil) — arka planda variant_group_id ile bağlıdır. */
    public function scopeOptionsOf(Builder $query, self $product): void
    {
        $product->variant_group_id
            ? $query->where('variant_group_id', $product->variant_group_id)
            : $query->whereKey($product->id);
    }
}
