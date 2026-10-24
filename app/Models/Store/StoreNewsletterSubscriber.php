<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;

/** E-bülten abonesi. Durum: pending (onay e-postası bekleniyor) → active → unsubscribed. */
class StoreNewsletterSubscriber extends Model
{
    public const STATUSES = ['active' => 'Abone', 'pending' => 'Onay bekliyor', 'unsubscribed' => 'Ayrıldı'];

    public const SOURCES = ['footer' => 'Alt bilgi formu', 'register' => 'Üyelik formu', 'account' => 'Hesap ayarları', 'admin' => 'Yönetim'];

    protected $attributes = ['status' => 'pending', 'source' => 'footer'];

    protected $fillable = ['email', 'status', 'token', 'customer_id', 'source', 'consent_text', 'consent_ip', 'consented_at', 'confirmed_at', 'unsubscribed_at'];

    protected function casts(): array
    {
        return ['consented_at' => 'datetime', 'confirmed_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'pending' => 'warning',
            default => 'neutral',
        };
    }
}
