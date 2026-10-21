<?php

use App\Models\BusinessUser;
use App\Models\DealerUser;
use App\Models\PlatformAdmin;
use App\Models\Store\StoreAdmin;

/*
| Ayrı giriş alanları (B2B: platform, toptancı, bayi; mağaza: yönetim paneli): her biri kendi kullanıcı tablosu ve guard'ı ile çalışır.
| Ortak bir "users" tablosu yoktur; bu yüzden bir alanın hesabı diğer alanda oturum açamaz.
| Aktivasyon ve parola sıfırlama bağlantıları App\Domain\Auth\AuthTokenService ile üretilir.
*/

return [

    'defaults' => [
        'guard' => 'business',
        'passwords' => null,
    ],

    'guards' => [
        'platform' => ['driver' => 'session', 'provider' => 'platform_admins'],
        'business' => ['driver' => 'session', 'provider' => 'business_users'],
        'dealer' => ['driver' => 'session', 'provider' => 'dealer_users'],
        'store_admin' => ['driver' => 'session', 'provider' => 'store_admins'],
    ],

    'providers' => [
        'platform_admins' => ['driver' => 'eloquent', 'model' => PlatformAdmin::class],
        'business_users' => ['driver' => 'eloquent', 'model' => BusinessUser::class],
        'dealer_users' => ['driver' => 'eloquent', 'model' => DealerUser::class],
        'store_admins' => ['driver' => 'eloquent', 'model' => StoreAdmin::class],
    ],

    'passwords' => [],

    'password_timeout' => 10800,

];
