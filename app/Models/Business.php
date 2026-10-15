<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Business extends Model
{
    public const STATUSES = ['active' => 'Aktif', 'suspended' => 'Askıda', 'closed' => 'Kapatıldı'];

    protected $fillable = [
        'slug', 'legal_name', 'display_name', 'tax_office', 'tax_number', 'phone', 'email',
        'city', 'district', 'address', 'logo_path', 'accent_color', 'status', 'status_reason',
    ];

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function user(): HasOne
    {
        return $this->hasOne(BusinessUser::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(BusinessSetting::class);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class)->orderByDesc('starts_on');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(BusinessModule::class);
    }

    public function currentLicense(): ?License
    {
        $today = now()->toDateString();

        return $this->licenses->first(
            fn (License $l) => $l->starts_on->toDateString() <= $today
                && ($l->ends_on === null || $l->ends_on->toDateString() >= $today)
        );
    }

    public function moduleEnabled(string $key): bool
    {
        $row = $this->modules->firstWhere('module_key', $key);

        return $row === null || $row->enabled;
    }
}
