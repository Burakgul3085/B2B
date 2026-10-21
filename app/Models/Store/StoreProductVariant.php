<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satılabilir birim: SKU, barkod, KDV dahil fiyat. Stok (M3) varyant üzerinden tutulur. */
class StoreProductVariant extends Model
{
    public const VAT_RATES = ['0', '1', '10', '20'];

    protected $attributes = ['is_active' => true, 'sort' => 0, 'barcode' => null, 'option_value' => null, 'compare_at_price' => null, 'desi' => null, 'weight_grams' => null];

    protected $fillable = ['product_id', 'sku', 'barcode', 'option_value', 'price', 'compare_at_price', 'vat_rate', 'desi', 'weight_grams', 'is_active', 'sort'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'weight_grams' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class, 'product_id');
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
