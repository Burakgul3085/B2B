<?php

namespace App\Domain\Auth;

use App\Models\BusinessUser;
use App\Models\DealerUser;
use App\Models\PlatformAdmin;
use App\Models\Store\StoreAdmin;
use Illuminate\Http\Request;

/**
 * Ayrı giriş alanları. Her alanın kendi guard'ı ve oturum çerezi vardır.
 *  - B2B alanları (platform, toptancı, bayi) URL önekiyle ayrılır; çerez yalnızca kendi önekine gönderilir.
 *  - Mağaza yönetimi kendi alan adında çalışır (config store.admin_domain); çerezi yalnızca o alan adına gider.
 * Böylece alanlar birbirinin oturumunu göremez.
 */
enum Area: string
{
    case Platform = 'platform';
    case Business = 'business';
    case Dealer = 'dealer';
    case StoreAdmin = 'store_admin';

    /** URL öneki. Mağaza yönetimi kendi alan adının kökünde çalışır. */
    public function prefix(): string
    {
        return match ($this) {
            self::Platform => 'platform',
            self::Business => 'toptanci',
            self::Dealer => 'bayi',
            self::StoreAdmin => '',
        };
    }

    public function guard(): string
    {
        return $this->value;
    }

    public function cookieName(): string
    {
        return $this === self::StoreAdmin ? 'magaza_yonetim' : 'b2b_'.$this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Platform => 'Platform Yönetimi',
            self::Business => 'Toptancı Paneli',
            self::Dealer => 'Bayi Paneli',
            self::StoreAdmin => 'Mağaza Yönetimi',
        };
    }

    /** @return class-string */
    public function model(): string
    {
        return match ($this) {
            self::Platform => PlatformAdmin::class,
            self::Business => BusinessUser::class,
            self::Dealer => DealerUser::class,
            self::StoreAdmin => StoreAdmin::class,
        };
    }

    public function loginRoute(): string
    {
        return $this->value.'.login';
    }

    /** Girişten sonra açılan sayfa. Bayi vitrin ana sayfasına düşer. */
    public function homeRoute(): string
    {
        return $this === self::Dealer ? 'dealer.home' : $this->value.'.dashboard';
    }

    public static function fromRequest(Request $request): ?self
    {
        $host = $request->getHost();
        if ($host === config('store.admin_domain')) {
            return self::StoreAdmin;
        }
        if (self::isStoreHost($request)) {
            return null; // herkese açık mağaza; B2B alanı değildir
        }

        $first = explode('/', trim($request->path(), '/'))[0] ?? '';

        foreach ([self::Platform, self::Business, self::Dealer] as $area) {
            if ($first === $area->prefix()) {
                return $area;
            }
        }

        return null;
    }

    /** İstek mağazanın veya mağaza yönetiminin alan adına mı geldi? B2B rotaları bu alan adlarında açılmaz. */
    public static function isStoreHost(Request $request): bool
    {
        return in_array($request->getHost(), [config('store.domain'), config('store.admin_domain')], true);
    }
}
