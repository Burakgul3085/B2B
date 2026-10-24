<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Toplu e-posta (e-bülten kampanyası). Durum: draft → sending → sent | cancelled. Gönderim başladıktan sonra düzenlenemez. */
class StoreNewsletterCampaign extends Model
{
    public const STATUSES = ['draft' => 'Taslak', 'sending' => 'Gönderiliyor', 'sent' => 'Gönderildi', 'cancelled' => 'Durduruldu'];

    protected $attributes = ['status' => 'draft', 'recipients_count' => 0, 'sent_count' => 0, 'failed_count' => 0];

    protected $fillable = ['subject', 'preheader', 'body', 'button_label', 'button_url'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime', 'recipients_count' => 'integer', 'sent_count' => 'integer', 'failed_count' => 'integer'];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(StoreNewsletterDelivery::class, 'campaign_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'sending' => 'info',
            'sent' => 'success',
            'cancelled' => 'warning',
            default => 'neutral',
        };
    }

    /** Yüzde olarak ilerleme (0–100). */
    public function progress(): int
    {
        return $this->recipients_count ? (int) floor(($this->sent_count + $this->failed_count) / $this->recipients_count * 100) : 0;
    }
}
