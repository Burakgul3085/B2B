<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * İade talebi (cayma hakkı). Akış:
 *   requested → approved → received → refunded
 *   requested → rejected
 */
class StoreReturn extends Model
{
    public const STATUSES = [
        'requested' => 'Talep alındı',
        'approved' => 'Onaylandı, ürün bekleniyor',
        'received' => 'Ürün teslim alındı',
        'refunded' => 'Para iadesi yapıldı',
        'rejected' => 'Reddedildi',
    ];

    public const OPEN = ['requested', 'approved', 'received'];

    public const REASONS = [
        'changed_mind' => 'Vazgeçtim',
        'damaged' => 'Ürün hasarlı geldi',
        'wrong_item' => 'Yanlış ürün gönderildi',
        'not_as_described' => 'Ürün açıklamadaki gibi değil',
        'other' => 'Diğer',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'received_at' => 'datetime', 'refunded_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StoreReturnItem::class, 'return_id')->orderBy('id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(StoreRefund::class, 'return_id')->orderBy('id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'refunded' => 'success',
            'requested' => 'warning',
            'rejected' => 'danger',
            default => 'info',
        };
    }
}
