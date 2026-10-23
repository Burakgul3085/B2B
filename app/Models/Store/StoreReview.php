<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ürün yorumu. Yalnızca ürünü teslim almış üye yazabilir; yönetici onaylamadan yayımlanmaz.
 * Durum: pending (onay bekliyor) → approved (yayında) | rejected (yayımlanmadı).
 */
class StoreReview extends Model
{
    public const STATUSES = ['pending' => 'Onay bekliyor', 'approved' => 'Yayında', 'rejected' => 'Yayımlanmadı'];

    protected $attributes = ['status' => 'pending'];

    protected $fillable = ['product_id', 'customer_id', 'author_name', 'rating', 'title', 'body', 'status', 'reply', 'moderated_at'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'moderated_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class, 'product_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(StoreCustomer::class, 'customer_id');
    }

    public function scopeApproved(Builder $query): void
    {
        $query->where('status', 'approved');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'approved' => 'success',
            default => 'neutral',
        };
    }
}
