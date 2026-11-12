<?php

namespace App\Models\Store;

use App\Domain\Store\Shipping\CarrierBrands;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Mağaza siparişi. Adres, fatura ve fiyatlar sipariş anındaki kopyadır.
 * Durumlar (docs/13-magaza-mimarisi.md §8.3):
 *   kart:      pending_payment → paid → preparing → shipped → delivered   (ödeme başarısız: payment_failed)
 *   teslimde:  awaiting_approval → preparing → shipped → delivered        (teslim alınmadı: not_delivered)
 *              (yalnızca Eskişehir: mağazadan teslim alda "mağazada", eve teslimde "kapıda" nakit / POS)
 *   mağazadan teslim alda "shipped" durumu "Teslime hazır" olarak gösterilir.
 *   havale:    awaiting_transfer → preparing → shipped → delivered        (süresinde ödenmezse: cancelled)
 *   hepsi:     yola çıkmadan cancelled
 */
class StoreOrder extends Model
{
    public const STATUSES = [
        'pending_payment' => 'Ödeme bekleniyor',
        'payment_failed' => 'Ödeme alınamadı',
        'awaiting_approval' => 'Onay bekliyor',
        'awaiting_transfer' => 'Havale bekleniyor',
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
        'cod_pending' => 'Teslimde ödenecek',
        'collected' => 'Tahsil edildi',
        'void' => 'Tahsil edilmedi',
        'partially_refunded' => 'Kısmen iade edildi',
        'refunded' => 'İade edildi',
    ];

    /** Yönetim listeleri ve filtreler için genel adlar; müşteriye paymentLabel() gösterilir ("Mağazada / Kapıda ödeme"). */
    public const PAYMENT_METHODS = ['card' => 'Kredi / banka kartı', 'cod' => 'Teslimatta ödeme', 'transfer' => 'Havale / EFT'];

    /** "local" eski siparişlerde de eve teslim (Eskişehir, kendi aracımızla) anlamındadır. */
    public const DELIVERY_METHODS = ['cargo' => 'Kargo', 'pickup' => 'Mağazadan teslim al', 'local' => 'Eve teslim (Eskişehir)'];

    /** Teslim edilmemiş, iptal edilmemiş siparişler. */
    public const OPEN = ['pending_payment', 'awaiting_approval', 'awaiting_transfer', 'paid', 'preparing', 'shipped'];

    /** Yola çıkmadan iptal edilebilen durumlar. */
    public const CANCELLABLE = ['pending_payment', 'awaiting_approval', 'awaiting_transfer', 'paid', 'preparing'];

    /** Müşterinin kendisi iptal edebildiği durumlar: kargoya verilmeden önce (ödeme sürerken değil). */
    public const CUSTOMER_CANCELLABLE = ['awaiting_approval', 'awaiting_transfer', 'paid', 'preparing'];

    public const CANCEL_REASONS = [
        'changed_mind' => 'Vazgeçtim',
        'wrong_choice' => 'Yanlış ürün veya adet seçtim',
        'address' => 'Teslimat adresini değiştirmem gerekiyor',
        'too_slow' => 'Teslimat süresi uzun',
        'bought_elsewhere' => 'Başka yerden aldım',
        'other' => 'Diğer',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime', 'approved_at' => 'datetime', 'shipped_at' => 'datetime',
            'delivered_at' => 'datetime', 'cancelled_at' => 'datetime', 'alerted_at' => 'datetime', 'cancel_requested_at' => 'datetime',
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

    public function carrierModel(): BelongsTo
    {
        return $this->belongsTo(StoreCarrier::class, 'carrier_id');
    }

    /** Kargo firmasının logosu (firma adından; firma sonradan silinse de çalışır). */
    public function carrierLogo(): ?string
    {
        return CarrierBrands::match($this->carrier)['logo'] ?? null;
    }

    /** Takip bağlantısı numarayı içeriyorsa kargo sayfası gönderiyi doğrudan gösterir; değilse numara sayfada girilir. */
    public function trackingIsDirect(): bool
    {
        return $this->tracking_url && $this->tracking_number && str_contains($this->tracking_url, rawurlencode($this->tracking_number));
    }

    public function returns(): HasMany
    {
        return $this->hasMany(StoreReturn::class, 'order_id')->orderBy('id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(StoreRefund::class, 'order_id')->orderBy('id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(StoreCreditNote::class, 'order_id')->orderBy('id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(StoreInvoice::class, 'order_id');
    }

    /** Faturalar ekranında hazırlanan e-Fatura / e-Arşiv belgeleri (taslak, gönderilen, iptal). */
    public function eInvoices(): HasMany
    {
        return $this->hasMany(StoreEInvoice::class, 'order_id')->orderBy('id');
    }

    /** Ödenen (kart) veya tahsil edilen (kapıda) tutar; iade bunun üzerinden yapılabilir. */
    /** Müşteri iptal düğmesini görür mü: kargoya verilmemiş ve bekleyen bir iptal talebi yok. */
    public function customerCancellable(): bool
    {
        return in_array($this->status, self::CUSTOMER_CANCELLABLE, true) && $this->cancel_requested_at === null;
    }

    public function isSettled(): bool
    {
        return in_array($this->payment_status, ['paid', 'collected', 'partially_refunded'], true);
    }

    /** Para iadesi müşterinin banka hesabına havaleyle (elle) yapılır: kapıda ödeme ve havale. Kartta iyzico'dan karta. */
    public function refundsToBank(): bool
    {
        return $this->payment_method !== 'card';
    }

    /** İade edilebilecek kalan tutar. */
    public function refundableAmount(): string
    {
        return $this->isSettled() ? bcsub((string) $this->total, (string) $this->refunded_total, 2) : '0.00';
    }

    /** Sipariş geçmişi satırı: durum değişikliği veya iade / para iadesi / fatura kaydı. */
    public static function eventLabel(string $status): string
    {
        return self::STATUSES[$status] ?? match ($status) {
            'return' => 'İade',
            'cancel_request' => 'İptal talebi',
            'payment_duplicate' => 'Fazla ödeme',
            'refund' => 'Para iadesi',
            'invoice' => 'Fatura',
            default => $status,
        };
    }

    public function statusLabel(): string
    {
        return self::statusLabelFor($this->status, $this->delivery_method);
    }

    /** Mağazadan teslim alınacak siparişte "Yolda" yerine "Teslime hazır" yazılır. */
    public static function statusLabelFor(string $status, ?string $deliveryMethod): string
    {
        if ($status === 'shipped' && $deliveryMethod === 'pickup') {
            return 'Teslime hazır';
        }

        return self::eventLabel($status);
    }

    public function paymentLabel(): string
    {
        return self::paymentLabelFor($this->payment_method, $this->delivery_method);
    }

    /** Teslimde ödeme müşteriye teslimat yöntemine göre adlandırılır: mağazadan teslim alda "Mağazada ödeme", eve teslimde "Kapıda ödeme". */
    public static function paymentLabelFor(string $paymentMethod, ?string $deliveryMethod): string
    {
        if ($paymentMethod === 'cod') {
            return $deliveryMethod === 'pickup' ? 'Mağazada ödeme' : 'Kapıda ödeme';
        }

        return self::PAYMENT_METHODS[$paymentMethod] ?? $paymentMethod;
    }

    public function deliveryLabel(): string
    {
        return self::DELIVERY_METHODS[$this->delivery_method] ?? $this->delivery_method;
    }

    public function isPickup(): bool
    {
        return $this->delivery_method === 'pickup';
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'delivered', 'paid' => 'success',
            'pending_payment', 'awaiting_approval', 'awaiting_transfer' => 'warning',
            'payment_failed', 'not_delivered', 'cancelled' => 'danger',
            default => 'info',
        };
    }

    public function shippingOneLine(): string
    {
        return implode(', ', array_filter([$this->shipping_neighborhood, $this->shipping_address])).' — '.$this->shipping_district.' / '.$this->shipping_province;
    }
}
