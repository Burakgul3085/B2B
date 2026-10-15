# B2B Toptan Ticaret ve Bayi Yönetim Platformu

Laravel 13 + MySQL 8.4 + Blade/Alpine/Tailwind 4. Mimari ve iş kuralları `docs/` altındadır; kod bu dokümanlara uyar.

## Değişmez kurallar

- Üç giriş alanı: `/platform` (PlatformAdmin, guard `platform`), `/toptanci` (BusinessUser, guard `business`), `/bayi` (DealerUser, guard `dealer`). Ortak `users` tablosu yoktur.
- İşletmeye ait her model `App\Domain\Tenancy\BelongsToBusiness` trait'ini kullanır. İşletme bağlamı yalnızca oturumdan kurulur (`SetBusinessContext`, `SetDealerContext`); URL/form değeri kullanılmaz.
- Stok ve cari hareketleri yalnızca eklenir; düzeltme ters kayıtla yapılır. Rezervasyon, kargo, fire modülü yoktur.
- Her işletmede tek kullanıcı, her bayide tek kullanıcı; rol sistemi yoktur.
- İş işlemleri `app/Domain/*` altındaki Action/servis sınıflarındadır; controller yalnızca doğrular ve çağırır.

## Komutlar

- Testler (MySQL `b2b_test` veritabanı gerekir): `php artisan test`
- Kod stili: `vendor/bin/pint`
- Ön yüz derleme: `npm run build`
- Yerel kurulum ve çalıştırma: `docs/GELISTIRME.md`
