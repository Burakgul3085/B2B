<?php

return [
    // Giriş sayfalarında görünen marka. Arka plan fotoğrafı isteğe bağlıdır:
    // public/images/giris-{bayi|toptanci|platform}.jpg ya da hepsi için public/images/giris.jpg
    'brand' => [
        'name' => env('BRAND_NAME', env('APP_NAME', 'B2B Platform')),
        'tagline' => env('BRAND_TAGLINE', 'Toptan ticaret ve bayi yönetimi'),
    ],

    // Platform paneline erişebilecek IP adresleri (boşsa kısıt yok).
    'platform_allowed_ips' => env('PLATFORM_ALLOWED_IPS', ''),

    // İşletme Gmail uygulama şifrelerini şifrelemek için APP_KEY'den ayrı anahtar.
    'mail_secret_key' => env('MAIL_SECRET_KEY'),

    // Platformun kendi e-postaları (işletme sahibi daveti, platform parola sıfırlama).
    // Toptancıların bayilerine gönderdiği e-postalar için KULLANILMAZ (karar K9).
    'system_mail' => [
        'host' => env('SYSTEM_MAIL_HOST'),
        'port' => (int) env('SYSTEM_MAIL_PORT', 587),
        'username' => env('SYSTEM_MAIL_USERNAME'),
        'password' => env('SYSTEM_MAIL_PASSWORD'),
        'from_address' => env('SYSTEM_MAIL_FROM_ADDRESS'),
        'from_name' => env('SYSTEM_MAIL_FROM_NAME', 'B2B Platform'),
    ],

    'activation_ttl_hours' => 72,
    'password_reset_ttl_minutes' => 60,

    'login_limits' => [
        'per_email' => 5,   // e-posta + IP başına
        'per_ip' => 30,     // IP başına
        'decay_seconds' => 900,
    ],
];
