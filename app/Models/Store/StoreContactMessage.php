<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** İletişim sayfasındaki formdan gelen mesaj. Durum: new (yeni) → read (okundu) → replied (yanıtlandı). */
class StoreContactMessage extends Model
{
    public const STATUSES = ['new' => 'Yeni', 'read' => 'Okundu', 'replied' => 'Yanıtlandı'];

    public const SUBJECTS = [
        'Sipariş' => 'Siparişim hakkında',
        'Ürün' => 'Ürün sorusu',
        'İade' => 'İade ve değişim',
        'Toplu alım' => 'Toplu / kurumsal alım',
        'Diğer' => 'Diğer',
    ];

    protected $attributes = ['status' => 'new'];

    protected $fillable = ['name', 'email', 'phone', 'subject', 'message', 'status', 'read_at', 'replied_at', 'ip', 'user_agent'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'replied_at' => 'datetime'];
    }

    /** Yönetimden e-postayla gönderilen yanıtlar */
    public function replies(): HasMany
    {
        return $this->hasMany(StoreContactReply::class, 'contact_message_id')->orderBy('id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'new' => 'warning',
            'replied' => 'success',
            default => 'info',
        };
    }
}
