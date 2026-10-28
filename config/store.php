<?php

/*
| Mağaza (B2C) — bkz. docs/13-magaza-mimarisi.md.
| Mağaza ve yönetim paneli kendi alan adlarında çalışır; B2B rotaları bu alan adlarında açılmaz.
| Yerelde *.localhost adresleri ek ayar gerektirmeden çalışır.
*/
return [
    'name' => env('STORE_NAME', 'Yapboz Kırtasiye'),

    // Herkese açık mağaza (ör. yapbozkirtasiye.com)
    'domain' => env('STORE_DOMAIN', 'magaza.localhost'),

    // Mağaza yönetim paneli (ör. yonetim.yapbozkirtasiye.com)
    'admin_domain' => env('STORE_ADMIN_DOMAIN', 'yonetim.localhost'),

    // Mağaza formlarında bot koruması (tuzak alanı + en kısa doldurma süresi). Yalnızca testlerde kapatılır.
    'bot_protection' => (bool) env('STORE_BOT_PROTECTION', true),

    // Google Analytics 4 ölçüm kimliği (yalnızca vitrin). Betik, ziyaretçi analitik çerezlere izin verirse yüklenir.
    // Boşsa kapalıdır; üretimde varsayılan mağazanın kimliğidir, yerelde ve testlerde kapalıdır.
    'analytics_id' => env('STORE_ANALYTICS_ID', env('APP_ENV') === 'production' ? 'G-P86EVFT132' : null),

    // Fotokopi: belge çevirme ve sayfa sayımı araçları (sunucuda: apt install libreoffice-core-nogui poppler-utils).
    'print' => [
        'soffice' => env('PRINT_SOFFICE', 'soffice'),
        'pdfinfo' => env('PRINT_PDFINFO', 'pdfinfo'),
        'pdftoppm' => env('PRINT_PDFTOPPM', 'pdftoppm'),
        'convert_timeout' => (int) env('PRINT_CONVERT_TIMEOUT', 300),
        'chunk_bytes' => 8 * 1024 * 1024, // tarayıcı dosyayı bu boyutta parçalarla yükler
    ],

    // Alt bilgideki yazılım geliştirici künyesi (sabit)
    'developer' => [
        'name' => 'Burak Gül',
        'email' => 'burakgul3085@gmail.com',
        'linkedin' => 'https://www.linkedin.com/in/burakgul1006/',
    ],
];
