<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * B2B faturası (satış veya iade; e-Fatura / e-Arşiv). Tutarlar siparişten (iadede iade kaydından) birebir gelir ve
 * değiştirilemez; taslakta yalnızca alıcı bilgileri, açıklama ve tarih düzeltilir. Gönderilen fatura değişmez,
 * e-Arşiv 7 gün içinde iptal edilebilir. Bkz. App\Domain\Invoicing\InvoiceService.
 */
class Invoice extends Model
{
    use BelongsToBusiness;

    public const STATUSES = [
        'draft' => 'Taslak',
        'sending' => 'Gönderiliyor',
        'unknown' => 'Sonuç belirsiz',
        'sent' => 'Kesildi',
        'cancelled' => 'İptal edildi',
        'failed' => 'Reddedildi',
    ];

    /** Kapanmış belgeler: siparişe yeni belge hazırlanmasını engellemez. */
    public const CLOSED = ['cancelled', 'failed'];

    /** Sonucu henüz kesinleşmemiş belgeler (gönderiliyor / sonuç belirsiz). */
    public const UNSETTLED = ['sending', 'unknown'];

    public const KINDS = ['sale' => 'Satış faturası', 'refund' => 'İade faturası'];

    public const DOCUMENT_TYPES = ['e_arsiv' => 'e-Arşiv', 'e_fatura' => 'e-Fatura'];

    public const PROVIDERS = ['deneme' => 'Deneme', 'uyumsoft' => 'Uyumsoft', 'manual' => 'Elle (Uyumsoft portalı)'];

    protected $guarded = ['id', 'business_id', 'open_key'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date', 'sent_at' => 'datetime', 'cancelled_at' => 'datetime', 'provider_checked_at' => 'datetime',
            'dealer_notified_at' => 'datetime', 'notify_dealer' => 'boolean',
            'subtotal' => 'decimal:2', 'vat_total' => 'decimal:2', 'total' => 'decimal:2', 'expected_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('position');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isManual(): bool
    {
        return $this->source === 'manual';
    }

    public function statusLabel(): string
    {
        return $this->status === 'draft' && $this->error_message ? 'Sorunlu' : (self::STATUSES[$this->status] ?? $this->status);
    }

    public function statusTone(): string
    {
        return match (true) {
            $this->status === 'sent' => 'success',
            $this->status === 'cancelled' => 'neutral',
            in_array($this->status, ['unknown', 'failed'], true), $this->status === 'draft' && $this->error_message !== null => 'danger',
            default => 'warning',
        };
    }

    public function label(): string
    {
        return self::KINDS[$this->kind].($this->document_type ? ' · '.self::DOCUMENT_TYPES[$this->document_type] : '');
    }
}
