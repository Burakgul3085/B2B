<?php

namespace App\Models\Store;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Mağaza yönetim paneli kullanıcısı. Tek ayrım süper admin bayrağıdır: yalnızca süper admin yönetici açar,
 * pasife alır ve şifresini sıfırlar. Süper admini yalnızca platform yönetir.
 */
class StoreAdmin extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password', 'is_super', 'is_active', 'created_by_type', 'created_by_id'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    /** Yeni oluşturulan kayıt veritabanından yeniden okunmadan da kullanılabilsin. */
    protected $attributes = [
        'is_super' => false,
        'is_active' => true,
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
        'last_login_at' => null,
        'remember_token' => null,
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_super' => 'boolean',
            'is_active' => 'boolean',
            'orders_seen_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function canSignIn(): bool
    {
        return $this->is_active;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /** Süper admin için zorunlu; yönetici kendisi açtıysa her girişte istenir. */
    public function requiresTwoFactor(): bool
    {
        return $this->is_super || $this->hasTwoFactorEnabled();
    }

    public function roleLabel(): string
    {
        return $this->is_super ? 'Süper admin' : 'Yönetici';
    }
}
