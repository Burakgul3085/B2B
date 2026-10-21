<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

/** Mağaza müşterisi (guard "customer"). B2B bayi hesaplarından ayrıdır; bir bayi mağazada ayrı hesapla alışveriş yapar. */
class StoreCustomer extends Authenticatable
{
    protected $attributes = ['is_active' => true, 'phone' => null, 'kvkk_accepted_at' => null, 'marketing_consent_at' => null, 'last_login_at' => null, 'remember_token' => null];

    protected $fillable = ['first_name', 'last_name', 'email', 'phone', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'kvkk_accepted_at' => 'datetime',
            'marketing_consent_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function canSignIn(): bool
    {
        return $this->is_active;
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /** "Profil ve Güvenlik" gibi ortak ekranlar için ad. */
    public function getNameAttribute(): string
    {
        return $this->fullName();
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(StoreCustomerAddress::class, 'customer_id')->orderByDesc('is_default')->orderBy('id');
    }

    public function cart(): HasOne
    {
        return $this->hasOne(StoreCart::class, 'customer_id');
    }
}
