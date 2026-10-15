<?php

namespace App\Domain\Auth;

use App\Models\BusinessUser;
use App\Models\DealerUser;
use App\Models\PlatformAdmin;
use Illuminate\Http\Request;

/**
 * Üç ayrı giriş alanı. Her alanın kendi guard'ı, URL öneki ve oturum çerezi vardır;
 * çerez yalnızca kendi URL önekine gönderilir, böylece alanlar birbirinin oturumunu göremez.
 */
enum Area: string
{
    case Platform = 'platform';
    case Business = 'business';
    case Dealer = 'dealer';

    public function prefix(): string
    {
        return match ($this) {
            self::Platform => 'platform',
            self::Business => 'toptanci',
            self::Dealer => 'bayi',
        };
    }

    public function guard(): string
    {
        return $this->value;
    }

    public function cookieName(): string
    {
        return 'b2b_'.$this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Platform => 'Platform Yönetimi',
            self::Business => 'Toptancı Paneli',
            self::Dealer => 'Bayi Paneli',
        };
    }

    /** @return class-string */
    public function model(): string
    {
        return match ($this) {
            self::Platform => PlatformAdmin::class,
            self::Business => BusinessUser::class,
            self::Dealer => DealerUser::class,
        };
    }

    public function loginRoute(): string
    {
        return $this->value.'.login';
    }

    public function homeRoute(): string
    {
        return $this->value.'.dashboard';
    }

    public static function fromRequest(Request $request): ?self
    {
        $first = explode('/', trim($request->path(), '/'))[0] ?? '';

        foreach (self::cases() as $area) {
            if ($first === $area->prefix()) {
                return $area;
            }
        }

        return null;
    }
}
