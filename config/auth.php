<?php

use App\Models\BusinessUser;
use App\Models\DealerUser;
use App\Models\PlatformAdmin;

/*
| Üç ayrı giriş alanı: her biri kendi kullanıcı tablosu ve guard'ı ile çalışır.
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
    ],

    'providers' => [
        'platform_admins' => ['driver' => 'eloquent', 'model' => PlatformAdmin::class],
        'business_users' => ['driver' => 'eloquent', 'model' => BusinessUser::class],
        'dealer_users' => ['driver' => 'eloquent', 'model' => DealerUser::class],
    ],

    'passwords' => [],

    'password_timeout' => 10800,

];
