<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * e-Fatura / e-Arşiv belgesi (satış veya iade). Taslakken düzenlenir; gönderildikten sonra değişmez, yalnızca
 * iptal edilebilir (e-Arşiv) ya da iade faturasıyla düzeltilir. Bkz. App\Domain\Store\Invoicing\EInvoiceService.
 */
class StoreEInvoice extends Model
{
    public const STATUSES = [
        'draft' => 'Taslak',
        'sending' => 'Gönderiliyor',
        'unknown' => 'Sonuç belirsiz',
        'sent' => 'Gönderildi',
        'cancelled' => 'İptal edildi',
    ];

    public const KINDS = ['sale' => 'Satış faturası', 'refund' => 'İade faturası'];

    public const DOCUMENT_TYPES = ['e_arsiv' => 'e-Arşiv', 'e_fatura' => 'e-Fatura'];

    public const PROVIDERS = ['deneme' => 'Deneme', 'uyumsoft' => 'Uyumsoft'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date', 'sent_at' => 'datetime', 'cancelled_at' => 'datetime',
            'difference_confirmed_at' => 'datetime', 'notify_customer' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'order_id');
    }

    public function return(): BelongsTo
    {
        return $this->belongsTo(StoreReturn::class, 'return_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StoreEInvoiceLine::class, 'invoice_id')->orderBy('position');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** Fatura toplamı ile siparişte ödenen (iade faturasında iade edilen) tutar arasındaki fark (fatura − beklenen). */
    public function difference(): string
    {
        return bcsub((string) $this->total, (string) $this->expected_total, 2);
    }

    public function hasDifference(): bool
    {
        return bccomp($this->difference(), '0', 2) !== 0;
    }

    public function statusLabel(): string
    {
        return $this->status === 'draft' && $this->error_message ? 'Hatalı' : (self::STATUSES[$this->status] ?? $this->status);
    }

    public function statusTone(): string
    {
        return match (true) {
            $this->status === 'sent' => 'success',
            $this->status === 'cancelled' => 'neutral',
            $this->status === 'unknown', $this->status === 'draft' && $this->error_message !== null => 'danger',
            default => 'warning',
        };
    }
}
