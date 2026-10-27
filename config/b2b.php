<?php

return [
    // Giriş sayfalarında görünen marka. Arka plan fotoğrafı isteğe bağlıdır:
    // public/images/giris-{bayi|toptanci|platform}.jpg ya da hepsi için public/images/giris.jpg
    'brand' => [
        'name' => env('BRAND_NAME', 'Yapboz Kırtasiye B2B'),
        'tagline' => env('BRAND_TAGLINE', 'Kırtasiye toptan sipariş platformu'),
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

    // Toptancı, bayi ve mağaza yönetimi girişinde e-postaya 4 haneli kod (App\Domain\Auth\LoginCode).
    // Acil durumda (ör. e-posta gönderimi bozulduysa) sunucuda LOGIN_EMAIL_CODE=false ile kapatılır.
    'login_code' => (bool) env('LOGIN_EMAIL_CODE', true),

    // Kısa adı (adres) değiştirilen toptancılar: eski => yeni. Başvuru formu, teşekkür sayfası ve logo adresleri
    // eski addan yenisine 301 ile yönlenir; paylaşılmış bağlantılar ve arama motoru kayıtları kırılmaz.
    'renamed_slugs' => [
        'perilikaya-mah-98140-no-cd-26c' => 'yapboz-kirtasiye', // 28.10.2026: işletme açılırken kısa ada adres yazılmıştı
    ],

    'login_limits' => [
        'per_email' => 5,   // e-posta + IP başına
        'per_ip' => 30,     // IP başına
        'decay_seconds' => 900,
    ],

    // Yedekleme (Platform → Yedekler, her gece 03:30). mysqldump sunucuda PATH'te; yerelde tam yolu verilir.
    'backup' => [
        'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),
        'keep' => (int) env('BACKUP_KEEP', 14),
    ],
];
