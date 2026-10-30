<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * İade talebi (cayma hakkı). Akış:
 *   requested → approved → shipped (müşteri kargo bilgisini girdi) → received → refunded
 *   requested → rejected;  approved → expired (süresinde kargolanmadı)
 * Yönetici ürünü kargo bilgisi gelmeden de teslim alabilir (approved → received).
 */
class StoreReturn extends Model
{
    public const STATUSES = [
        'requested' => 'Talep alındı',
        'approved' => 'Onaylandı, kargo bekleniyor',
        'shipped' => 'Kargoya verildi',
        'received' => 'Ürün teslim alındı',
        'refunded' => 'Para iadesi yapıldı',
        'rejected' => 'Reddedildi',
        'expired' => 'Süresi doldu',
    ];

    public const OPEN = ['requested', 'approved', 'shipped', 'received'];

    /** Müşterinin izlediği adımlar (sipariş sayfasındaki iade takibi). */
    public const STEPS = ['requested' => 'Talep alındı', 'approved' => 'Onaylandı', 'shipped' => 'Kargoda', 'received' => 'Teslim alındı', 'refunded' => 'İade yapıldı'];

    /** Bu nedenlerde (ayıplı / yanlış ürün) ürün fotoğrafı zorunludur. */
    public const SELLER_FAULT = ['damaged', 'wrong_item', 'not_as_described'];

    public const MAX_PHOTOS = 5;

    /** Sözleşme: müşteri ürünü iade bildiriminden (talep) itibaren SHIP_DAYS gün içinde gönderir. */
    public const SHIP_DAYS = 10;

    /** Talepten sonra kargo bilgisi yoksa hatırlatma; kargoda yolda olabileceği için kapanma birkaç gün sonra (gün). */
    public const REMIND_DAYS = 7;

    public const EXPIRE_DAYS = 14;

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
        return ['approved_at' => 'datetime', 'shipped_at' => 'datetime', 'reminded_at' => 'datetime', 'received_at' => 'datetime',
            'refunded_at' => 'datetime', 'closed_at' => 'datetime', 'cargo_fee' => 'decimal:2'];
    }

    public function photos(): HasMany
    {
        return $this->hasMany(StoreReturnPhoto::class, 'return_id')->orderBy('id');
    }

    public function sellerFault(): bool
    {
        return in_array($this->reason, self::SELLER_FAULT, true);
    }

    /** Müşteri ürünü bu tarihe kadar kargoya vermelidir (sözleşme: iade bildiriminden itibaren 10 gün). */
    public function shipDeadline(): Carbon
    {
        return $this->created_at->copy()->addDays(self::SHIP_DAYS);
    }

    /** İade kargosu mağazanın anlaşmalı firmasıyla ücretsiz mi (kod ayarlıysa), yoksa müşteri ödeyip ücret iadeye mi eklenir. */
    public static function returnCode(): ?array
    {
        $code = StoreSetting::get('return_code');

        return filled($code) && filled(StoreSetting::get('return_carrier')) ? ['carrier' => StoreSetting::get('return_carrier'), 'code' => $code] : null;
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
            'requested', 'shipped' => 'warning',
            'rejected', 'expired' => 'danger',
            default => 'info',
        };
    }
}
