<?php

namespace App\Models\Store;

use App\Domain\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Kupon (kodlu) veya otomatik sepet kampanyası (kodsuz). Kurallar: App\Domain\Store\Discounts\DiscountService. */
class StoreDiscount extends Model
{
    public const TYPES = ['percent' => 'Yüzde indirim', 'fixed' => 'Tutar indirimi', 'free_shipping' => 'Ücretsiz teslimat'];

    protected $guarded = ['id'];

    protected $attributes = ['is_active' => true, 'first_order_only' => false, 'codes_only' => false];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean', 'first_order_only' => 'boolean', 'codes_only' => 'boolean', 'show_on_home' => 'boolean',
            'usage_limit' => 'integer', 'per_customer_limit' => 'integer', 'category_ids' => 'array', 'product_ids' => 'array',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(StoreDiscountRedemption::class, 'discount_id');
    }

    public function codes(): HasMany
    {
        return $this->hasMany(StoreDiscountCode::class, 'discount_id')->orderBy('id');
    }

    public function isScoped(): bool
    {
        return ! empty($this->category_ids) || ! empty($this->product_ids);
    }

    public function isCoupon(): bool
    {
        return $this->code !== null;
    }

    /** "%10 indirim", "₺50 indirim", "Ücretsiz teslimat" (+ koşul). */
    public function summary(): string
    {
        $base = match ($this->type) {
            'percent' => '%'.rtrim(rtrim((string) $this->value, '0'), '.').' indirim'.($this->max_discount ? ' (en fazla '.Money::format($this->max_discount).')' : ''),
            'fixed' => Money::format($this->value).' indirim',
            default => 'Ücretsiz teslimat',
        };

        return $base.($this->isScoped() ? ' · seçili ürünlerde' : '').($this->min_subtotal ? ' · '.Money::format($this->min_subtotal).' ve üzeri' : '');
    }

    /** Bugün geçerli mi (etkin ve tarih aralığında)? */
    public function isLive(): bool
    {
        return $this->is_active && (! $this->starts_at || $this->starts_at->isPast()) && (! $this->ends_at || $this->ends_at->isFuture());
    }
}
