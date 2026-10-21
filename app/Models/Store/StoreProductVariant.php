<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satılabilir birim: SKU, barkod, KDV dahil fiyat ve stok. stock_quantity yalnızca StoreStockService üzerinden
 * değişir (hareket toplamının önbelleği); bu yüzden toplu atamaya kapalıdır.
 */
class StoreProductVariant extends Model
{
    public const VAT_RATES = ['0', '1', '10', '20'];

    protected $attributes = ['is_active' => true, 'sort' => 0, 'barcode' => null, 'option_value' => null, 'compare_at_price' => null,
        'desi' => null, 'weight_grams' => null, 'stock_quantity' => 0, 'critical_stock' => null];

    protected $fillable = ['product_id', 'sku', 'barcode', 'option_value', 'price', 'compare_at_price', 'vat_rate', 'desi', 'weight_grams', 'critical_stock', 'is_active', 'sort'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'weight_grams' => 'integer', 'stock_quantity' => 'integer', 'critical_stock' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class, 'product_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StoreStockMovement::class, 'variant_id');
    }

    public function holds(): HasMany
    {
        return $this->hasMany(StoreStockHold::class, 'variant_id');
    }

    /** Kritik stok seviyesi tanımlıysa ve stok bu seviyede ya da altındaysa. */
    public function isCritical(): bool
    {
        return $this->critical_stock !== null && $this->stock_quantity <= $this->critical_stock;
    }

    public function label(): string
    {
        return $this->option_value ? "{$this->option_value} ({$this->sku})" : $this->sku;
    }

    public function hasDiscount(): bool
    {
        return $this->compare_at_price !== null && bccomp((string) $this->compare_at_price, (string) $this->price, 2) > 0;
    }

    /** İndirim yüzdesi (tam sayı), üstü çizili fiyat yoksa null. */
    public function discountPercent(): ?int
    {
        if (! $this->hasDiscount()) {
            return null;
        }

        $saved = bcsub((string) $this->compare_at_price, (string) $this->price, 2);

        return (int) bcdiv(bcmul($saved, '100', 2), (string) $this->compare_at_price, 0);
    }
}
