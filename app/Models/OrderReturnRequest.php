<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Bayinin iade talebi (docs/03-is-kurallari.md §3.7). */
class OrderReturnRequest extends Model
{
    use BelongsToBusiness;

    public const STATUSES = [
        'requested' => 'Değerlendiriliyor',
        'approved' => 'Onaylandı, ürün bekleniyor',
        'received' => 'Teslim alındı, iade edildi',
        'rejected' => 'Reddedildi',
        'cancelled' => 'Geri çekildi',
    ];

    /** Açık talepler: talep edilen miktar, yeni talepte iade edilebilir miktardan düşülür. */
    public const OPEN = ['requested', 'approved'];

    public const REASONS = [
        'damaged' => 'Hasarlı / kırık geldi',
        'defective' => 'Kusurlu / çalışmıyor',
        'wrong_item' => 'Yanlış ürün gönderildi',
        'excess' => 'Fazla gönderildi',
        'expired' => 'Son kullanma tarihi geçmiş / yakın',
        'unsold' => 'Satılamadı / vazgeçildi',
        'other' => 'Diğer',
    ];

    /** Bu nedenlerde en az bir fotoğraf zorunludur. */
    public const PHOTO_REASONS = ['damaged', 'defective', 'wrong_item'];

    public const MAX_PHOTOS = 4;

    protected $guarded = ['id', 'business_id'];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'received_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(DealerRelationship::class, 'dealer_relationship_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnRequestItem::class)->orderBy('id');
    }

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class);
    }

    public function photos(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return ['requested' => 'warning', 'approved' => 'info', 'received' => 'success', 'rejected' => 'danger', 'cancelled' => 'neutral'][$this->status] ?? 'neutral';
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
