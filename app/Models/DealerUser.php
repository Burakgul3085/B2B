<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class DealerUser extends Authenticatable
{
    protected $fillable = ['dealer_id', 'name', 'email', 'password', 'status', 'activated_at'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'activated_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function canSignIn(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Bayinin çalışabileceği (ilişkisi ve toptancısı aktif) toptancı ilişkileri.
     * İşletme kapsamı burada bilerek kaldırılır; sorgu bayinin kendi dealer_id'siyle sınırlıdır.
     */
    public function usableRelationships(): Collection
    {
        return DealerRelationship::withoutBusinessScope()
            ->with('business')
            ->where('dealer_id', $this->dealer_id)
            ->where('status', 'active')
            ->whereHas('business', fn ($q) => $q->where('status', 'active'))
            ->orderBy('id')
            ->get();
    }
}
