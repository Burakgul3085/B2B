<?php

namespace App\Models\Store;

use App\Domain\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Kupon (kodlu) veya otomatik sepet kampanyası (kodsuz). Kurallar: App\Domain\Store\Discounts\DiscountService. */
class StoreDiscount extends Model
{
    public const TYPES = ['percent' => 'Yüzde indirim', 'fixed' => 'Tutar indirimi', 'free_shipping' => 'Ücretsiz teslimat'];

    /** Durum anahtarı => [etiket, rozet tonu] (yönetim listesi). */
    public const STATUSES = [
        'live' => ['Geçerli', 'success'], 'scheduled' => ['Başlamadı', 'info'], 'ended' => ['Süresi doldu', 'danger'],
        'limit' => ['Sınır doldu', 'warning'], 'inactive' => ['Pasif', 'neutral'],
    ];

    public const AUDIENCES = ['all' => 'Herkes', 'members' => 'Yalnızca üyeler', 'new_members' => 'Yeni üyeler (tek seferlik)'];

    protected $guarded = ['id'];

    protected $attributes = ['is_active' => true, 'first_order_only' => false, 'codes_only' => false, 'audience' => 'all', 'show_on_page' => false];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean', 'first_order_only' => 'boolean', 'codes_only' => 'boolean', 'show_on_home' => 'boolean',
            'show_on_page' => 'boolean', 'usage_limit' => 'integer', 'per_customer_limit' => 'integer', 'new_member_days' => 'integer',
            'category_ids' => 'array', 'product_ids' => 'array',
        ];
    }

    /** Etkin ve tarih aralığında olanlar (isLive ile aynı koşul). */
    public function scopeLive(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
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

        return $base.($this->isScoped() ? ' · seçili ürünlerde' : '').($this->min_subtotal ? ' · '.Money::format($this->min_subtotal).' ve üzeri' : '')
            .match ($this->audience) {
                'members' => ' · üyelere özel',
                'new_members' => ' · yeni üyelere özel',
                default => '',
            };
    }

    /** Müşteriye gösterilen koşul cümlesi: "1.500,00 ₺ ve üzeri alışverişlerde %5 indirim". */
    public function headline(): string
    {
        $benefit = match ($this->type) {
            'percent' => '%'.rtrim(rtrim((string) $this->value, '0'), '.').' indirim',
            'fixed' => Money::format($this->value).' indirim',
            default => 'ücretsiz kargo',
        };
        $where = $this->isScoped() ? 'seçili ürünlerde' : 'tüm ürünlerde';
        if ($this->min_subtotal) {
            $where = Money::format($this->min_subtotal).' ve üzeri '.($this->isScoped() ? 'kampanyalı ürün alışverişlerinde' : 'alışverişlerde');
        }

        return Str::ucfirst($where).' '.$benefit;
    }

    /** STATUSES anahtarı; $used: geçerli kullanım sayısı (sınır denetimi için). */
    public function status(?int $used = null): string
    {
        return match (true) {
            ! $this->is_active => 'inactive',
            (bool) $this->starts_at?->isFuture() => 'scheduled',
            (bool) $this->ends_at?->isPast() => 'ended',
            $this->usage_limit !== null && $used !== null && $used >= $this->usage_limit => 'limit',
            default => 'live',
        };
    }

    /** Bugün geçerli mi (etkin ve tarih aralığında)? */
    public function isLive(): bool
    {
        return $this->is_active && (! $this->starts_at || $this->starts_at->isPast()) && (! $this->ends_at || $this->ends_at->isFuture());
    }
}
