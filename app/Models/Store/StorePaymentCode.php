<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Özel ödeme kodu: tutarı yönetici belirler, müşteri kodu arama kutusuna yazıp öder. Tutar oluşturulduktan sonra
 * değiştirilemez (yanlışsa kod iptal edilip yenisi oluşturulur). Tek kullanımlıktır: açık bir siparişe bağlıysa kullanılmıştır;
 * sipariş iptal edilir veya ödemesi başarısız olursa yeniden kullanılabilir.
 */
class StorePaymentCode extends Model
{
    /** Sipariş bu durumlardaysa kod yeniden kullanılabilir. */
    public const RELEASING_ORDER_STATUSES = ['cancelled', 'payment_failed'];

    public const STATES = [
        'active' => ['Kullanılabilir', 'success'],
        'used' => ['Kullanıldı', 'info'],
        'expired' => ['Süresi doldu', 'neutral'],
        'cancelled' => ['Pasif', 'danger'],
    ];

    protected $fillable = ['code', 'title', 'amount', 'vat_rate', 'note', 'expires_at', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'vat_rate' => 'decimal:2', 'is_cancelled' => 'boolean', 'expires_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(StoreAdmin::class, 'created_by');
    }

    /** Kodu kullanan açık (iptal veya başarısız olmayan) sipariş. */
    public function liveOrder(): ?StoreOrder
    {
        $order = $this->order_id ? $this->order : null;

        return $order && ! in_array($order->status, self::RELEASING_ORDER_STATUSES, true) ? $order : null;
    }

    public function state(): string
    {
        return match (true) {
            $this->liveOrder() !== null => 'used',
            $this->is_cancelled => 'cancelled',
            $this->expires_at !== null && $this->expires_at->isPast() => 'expired',
            default => 'active',
        };
    }

    public function isUsable(): bool
    {
        return $this->state() === 'active';
    }

    /** Ekranda gösterim: ABCD-EFGH */
    public function display(): string
    {
        return substr($this->code, 0, 4).'-'.substr($this->code, 4);
    }
}
