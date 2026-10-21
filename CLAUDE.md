# B2B Toptan Ticaret ve Bayi Yönetim Platformu

Laravel 13 + MySQL 8.4 + Blade/Alpine/Tailwind 4. Mimari ve iş kuralları `docs/` altındadır; kod bu dokümanlara uyar. Mağaza (B2C) için: `docs/13-magaza-mimarisi.md`.

## Değişmez kurallar

- Sistem tek toptancı (Yapboz Kırtasiye) içindir: çok işletmeli altyapı korunur ama ikinci işletme açılamaz.
- Beş giriş alanı, ortak `users` tablosu yok:
  - B2B: `/platform` (PlatformAdmin, guard `platform`), `/toptanci` (BusinessUser, guard `business`), `/bayi` (DealerUser, guard `dealer`).
  - Mağaza: yönetim alt alan adı (StoreAdmin, guard `store_admin`) ve mağaza alan adı (StoreCustomer, guard `customer`).
- İşletmeye ait her B2B modeli `App\Domain\Tenancy\BelongsToBusiness` trait'ini kullanır. İşletme bağlamı yalnızca oturumdan kurulur (`SetBusinessContext`, `SetDealerContext`); URL/form değeri kullanılmaz.
- Mağaza ayrı bir modüldür (`app/Domain/Store`, `store_*` tabloları). B2B ile veri paylaşmaz; iki taraf birbirinin modellerini ve domain kodunu kullanamaz (mimari test denetler).
- Stok ve cari hareketleri yalnızca eklenir; düzeltme ters kayıtla yapılır. Fire modülü yoktur. Rezervasyon ve kargo yalnızca mağazada vardır; B2B'de yoktur.
- B2B'de her işletmede tek kullanıcı, her bayide tek kullanıcı; rol sistemi yoktur. İstisna: mağaza yönetiminde süper admin (`is_super`) ve sınırsız yönetici vardır.
- Mağazada fiyat, tutar ve ödeme sonucu her zaman sunucuda hesaplanır ve doğrulanır; tarayıcıdan gelen değere güvenilmez.
- İş işlemleri `app/Domain/*` altındaki Action/servis sınıflarındadır; controller yalnızca doğrular ve çağırır.

## Komutlar

- Testler (MySQL `b2b_test` veritabanı gerekir): `php artisan test` (eşzamanlılık testleri: `--testsuite=Concurrency`)
- Kod stili: `vendor/bin/pint`
- Ön yüz derleme: `npm run build`
- Yerel kurulum ve çalıştırma: `docs/GELISTIRME.md`
