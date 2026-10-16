# Yerel Geliştirme

## Gereksinimler

| Bileşen | Sürüm | Bu makinede |
|---|---|---|
| PHP | 8.3+ (pdo_mysql, bcmath, intl, mbstring, gd, fileinfo, zip) | `C:\tools\php85` (8.5.1) |
| Composer | 2.x | kurulu |
| MySQL | 8.4 LTS | `C:\tools\mysql84` (8.4.11, taşınabilir kurulum) |
| Node.js | 20+ | 22.14 (yalnızca ön yüz derlemesi için) |

## Tek komutla başlatma (Windows PowerShell)

Proje klasöründe:

```powershell
powershell -ExecutionPolicy Bypass -File .\baslat.ps1
```

MySQL çalışmıyorsa arka planda başlatır, ardından uygulamayı http://localhost:8000 adresinde açar.

## MySQL'i elle başlatma (Git Bash)

MySQL Windows hizmeti olarak kurulmadı (yönetici yetkisi gerektirir). Her oturumda elle başlatılır:

```bash
/c/tools/mysql84/bin/mysqld --defaults-file=C:/tools/mysql84/my.ini --console
```

- Yalnızca `127.0.0.1:3306` adresini dinler. `root` kullanıcısının parolası yoktur (yalnızca yerel geliştirme).
- Uygulama `b2b` kullanıcısıyla bağlanır; parola `.env` içindedir (`.env` git'e girmez).
- Veritabanları: `b2b` (geliştirme), `b2b_test` (testler).

## İlk kurulum (yeni makinede)

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
```

`.env` içinde `DB_PASSWORD` ve `MAIL_SECRET_KEY` (`php -r "echo 'base64:'.base64_encode(random_bytes(32));"`) doldurulur.

## Çalıştırma

```bash
php artisan migrate:fresh --seed
php artisan serve
```

Geliştirme verisi (`database/seeders/DevelopmentSeeder.php`, yalnızca `APP_ENV=local`):

| Alan | Adres | Hesap |
|---|---|---|
| Platform | http://localhost:8000/platform/login | `platform@b2b.test` — ilk girişte 2FA kurulumu istenir |
| Toptancı | http://localhost:8000/toptanci/login | `yonetici@akgun.test` (Akgün Kırtasiye), `yonetici@demotoptan.test` (Demo Toptan) |
| Bayi | http://localhost:8000/bayi/login | `onur@onurkirtasiye.test` — iki toptancıyla çalışır, toptancı seçim ekranı görünür |

Parolalar seeder dosyasındaki `DEV_PASSWORD` sabitindedir.

Sistem e-postası (`SYSTEM_MAIL_*`) tanımlı değilse yerel ortamda e-posta içeriği ve bağlantılar `storage/logs/laravel.log` dosyasına yazılır.

## Platform yöneticisi oluşturma (sunucuda)

```bash
php artisan platform:create-admin
```

Platform yöneticisi arayüzden oluşturulamaz.

## Testler

```bash
php artisan test
```

- `Unit`, `Feature` ve `Concurrency` paketleri birlikte çalışır (`b2b_test` veritabanı gerekir). Eşzamanlılık testleri gerçek ayrı PHP süreçleri başlatır.
- `tests/Feature/Security`: rota taraması (başka işletme/bayi kaydına erişim, oturum zorunluluğu), bozuk girdi taraması ve güvenlik sertleştirme testleri. Yeni eklenen rotalar taramalara kendiliğinden girer; yeni bir rota parametresi eklenirse `tests/Concerns/BuildsWorld.php` içine o modelden bir kayıt eklenmelidir (test bunu açıkça söyler).

### Performans ölçümü (büyük veri)

```bash
php artisan test --group=performance
```

300 bayi, 20.000 ürün ve 50.000 sipariş (~250.000 kalem) üretir, ana sayfaların ve raporların sürelerini ve sorgu sayılarını ölçer. Sonuçlar `storage/app/performans/sonuclar.md` dosyasına yazılır. Normal test koşusuna dahil değildir (yaklaşık 2–3 dakika). Boyutlar `PERF_DEALERS`, `PERF_PRODUCTS`, `PERF_ORDERS` ortam değişkenleriyle değiştirilebilir.
