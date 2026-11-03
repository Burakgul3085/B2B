<?php

namespace App\Models;

use App\Domain\Support\Money;
use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Bayilere kupon (kodlu) veya otomatik kampanya (kodsuz). Kurallar: App\Domain\Pricing\PromotionService. */
class Promotion extends Model
{
    use BelongsToBusiness;

    public const TYPES = ['percent' => 'Yüzde indirim', 'fixed' => 'Tutar indirimi'];

    public const TARGETS = ['all' => 'Tüm bayiler', 'group' => 'Bayi grubu', 'dealers' => 'Seçili bayiler'];

    /** Durum anahtarı => [etiket, rozet tonu]. */
    public const STATUSES = [
        'live' => ['Geçerli', 'success'], 'scheduled' => ['Başlamadı', 'info'], 'ended' => ['Süresi doldu', 'danger'],
        'limit' => ['Sınır doldu', 'warning'], 'inactive' => ['Pasif', 'neutral'],
    ];

    protected $guarded = ['id', 'business_id'];

    protected $attributes = ['is_active' => true, 'target' => 'all'];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2', 'max_discount' => 'decimal:2', 'min_subtotal' => 'decimal:2',
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean',
            'usage_limit' => 'integer', 'per_dealer_limit' => 'integer',
            'dealer_ids' => 'array', 'category_ids' => 'array', 'product_ids' => 'array',
        ];
    }

    /** Etkin ve tarih aralığında olanlar. */
    public function scopeLive(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(DealerGroup::class, 'dealer_group_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromotionRedemption::class);
    }

    public function isCoupon(): bool
    {
        return $this->code !== null;
    }

    public function isScoped(): bool
    {
        return ! empty($this->category_ids) || ! empty($this->product_ids);
    }

    /** Geçerli kullanım sayısı (reddedilen / iptal edilen siparişlerinki sayılmaz). */
    public function usedCount(): int
    {
        return $this->redemptions()->whereNull('released_at')->count();
    }

    public function status(?int $used = null): string
    {
        return match (true) {
            ! $this->is_active => 'inactive',
            $this->starts_at !== null && $this->starts_at->isFuture() => 'scheduled',
            $this->ends_at !== null && ! $this->ends_at->isFuture() => 'ended',
            $this->usage_limit !== null && ($used ?? $this->usedCount()) >= $this->usage_limit => 'limit',
            default => 'live',
        };
    }

    /** "%10 indirim (en fazla ₺500) · ₺5.000 ve üzeri · seçili ürünlerde" */
    public function summary(): string
    {
        $base = $this->type === 'percent'
            ? '%'.rtrim(rtrim((string) $this->value, '0'), '.').' indirim'.($this->max_discount ? ' (en fazla '.Money::format($this->max_discount).')' : '')
            : Money::format($this->value).' indirim';

        return $base.($this->isScoped() ? ' · seçili ürünlerde' : '')
            .($this->min_subtotal ? ' · '.Money::format($this->min_subtotal).' ve üzeri (KDV hariç)' : '');
    }
}
