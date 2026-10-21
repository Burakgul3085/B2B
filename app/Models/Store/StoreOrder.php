<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mağaza siparişi. Adres, fatura ve fiyatlar sipariş anındaki kopyadır.
 * Durumlar (docs/13-magaza-mimarisi.md §8.3):
 *   kart:      pending_payment → paid → preparing → shipped → delivered   (ödeme başarısız: payment_failed)
 *   kapıda:    awaiting_approval → preparing → shipped → delivered        (teslim alınmadı: not_delivered)
 *   her ikisi: yola çıkmadan cancelled
 */
class StoreOrder extends Model
{
    public const STATUSES = [
        'pending_payment' => 'Ödeme bekleniyor',
        'payment_failed' => 'Ödeme alınamadı',
        'awaiting_approval' => 'Onay bekliyor',
        'paid' => 'Ödendi',
        'preparing' => 'Hazırlanıyor',
        'shipped' => 'Yolda',
        'delivered' => 'Teslim edildi',
        'not_delivered' => 'Teslim alınmadı',
        'cancelled' => 'İptal edildi',
    ];

    public const PAYMENT_STATUSES = [
        'pending' => 'Bekleniyor',
        'paid' => 'Ödendi',
        'failed' => 'Başarısız',
        'cod_pending' => 'Kapıda tahsil edilecek',
        'collected' => 'Tahsil edildi',
        'void' => 'Tahsil edilmedi',
    ];

    public const PAYMENT_METHODS = ['card' => 'Kredi / banka kartı', 'cod' => 'Kapıda ödeme'];

    public const DELIVERY_METHODS = ['cargo' => 'Kargo', 'local' => 'Mağaza teslimatı (Eskişehir)'];

    /** Teslim edilmemiş, iptal edilmemiş siparişler. */
    public const OPEN = ['pending_payment', 'awaiting_approval', 'paid', 'preparing', 'shipped'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime', 'approved_at' => 'datetime', 'shipped_at' => 'datetime',
            'delivered_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(StoreCustomer::class, 'customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StoreOrderItem::class, 'order_id')->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(StoreOrderEvent::class, 'order_id')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(StorePayment::class, 'order_id')->orderBy('id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(StoreOrderConsent::class, 'order_id')->orderBy('id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'delivered', 'paid' => 'success',
            'pending_payment', 'awaiting_approval' => 'warning',
            'payment_failed', 'not_delivered', 'cancelled' => 'danger',
            default => 'info',
        };
    }

    public function shippingOneLine(): string
    {
        return implode(', ', array_filter([$this->shipping_neighborhood, $this->shipping_address])).' — '.$this->shipping_district.' / '.$this->shipping_province;
    }
}
