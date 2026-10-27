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

Toptancı, bayi ve mağaza yönetimi girişinde şifreden sonra e-posta kodu istenir. Yerelde e-posta hesabı tanımlı değilse kod gönderilmez, `storage/logs/laravel.log` dosyasına yazılır (toptancı ve bayi: `[business-mail]`, mağaza yöneticisi: `[store-login-code]` satırı). Kodu kapatmak için `.env`: `LOGIN_EMAIL_CODE=false`.

### Mağaza ve mağaza yönetimi (yerel adresler)

Mağaza kendi alan adlarında çalışır (`.env`: `STORE_DOMAIN`, `STORE_ADMIN_DOMAIN`). `*.localhost` adresleri tarayıcıda ek ayar gerektirmez:

| Alan | Adres |
|---|---|
| Mağaza | http://magaza.localhost:8000 |
| Mağaza yönetimi | http://yonetim.localhost:8000 |

İlk süper admin platformdan açılır: **Platform → Mağaza Yöneticileri → Yeni hesap** ("Süper admin" işaretli). Süper admin ilk girişte iki adımlı doğrulamayı kurar, ardından **Yöneticiler** sayfasından diğer yöneticileri açar. B2B adresleri (`/bayi`, `/toptanci`, `/platform`) mağaza alan adlarında açılmaz.

Mağaza varsayılan olarak **kapalıdır**; ziyaretçi "Çok yakında" sayfasını görür. Gezmek için mağaza yönetiminde **Vitrin → Mağaza Ayarları → Mağazayı önizle** (veya Genel Bakış'taki bağlantı) kullanılır; açmak için aynı sayfadaki "Mağazayı aç" düğmesi.

E-postalar işletmenin **ana Gmail hesabıyla** gönderilir (toptancı paneli → E-posta Ayarları veya platform → işletme detayı). E-postaların gitmesi için kuyruk çalıştırıcısı açık olmalıdır; `baslat.ps1` bunu otomatik başlatır.

## Yükleme sınırları (vitrin videoları)

Toptancı vitrin slaytlarına 50 MB'a kadar video yükleyebilir. PHP'nin varsayılan sınırı daha düşüktür; `php.ini` içinde:

```ini
upload_max_filesize = 64M
post_max_size = 64M
```

Sunucuda web sunucusu da aynı boyuta izin vermelidir (Nginx: `client_max_body_size 64m;`). Kod değiştikten sonra e-posta kuyruğunu yeniden başlatın (`php artisan queue:restart`), aksi hâlde işçi eski kodla çalışmaya devam eder.

Örnek vitrin (slaytlar ve ürün vitrinleri): `php artisan db:seed --class=StorefrontDemoSeeder` (vitrin boşsa yükler).

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

Aynı grupta mağaza ölçümü de vardır (`tests/Performance/StorePerformanceTest.php`): 10.000 ürün, 5.000 müşteri, 30.000 sipariş; vitrin, müşteri hesabı ve mağaza yönetimi sayfaları. Sonuçlar `storage/app/performans/magaza.md`. Boyutlar `PERF_STORE_PRODUCTS`, `PERF_STORE_CUSTOMERS`, `PERF_STORE_ORDERS`.

### Diğer denetimler

```bash
npm run denetim:alpine
```

Şablonlardaki Alpine ifadelerini CSP sürümünün ayrıştırıcısına karşı denetler (CSP'de `'unsafe-eval'` yoktur; mantık `resources/js/components.js` içinde).

## Yedekleme

```bash
php artisan backup:run
```

Veritabanı (mysqldump) ve `storage/app/private` dosyalarını tek zip olarak `storage/app/private/backups` altına yazar; zamanlayıcı her gece 03:30'da çalıştırır, son `BACKUP_KEEP` yedek saklanır. Windows'ta `.env` içinde `BACKUP_MYSQLDUMP="C:/tools/mysql84/bin/mysqldump.exe"` verilmelidir. Yedekler Platform → Yedekler ekranından da alınıp indirilebilir.

## Günlük (log) dosyaları

`.env.example` `LOG_STACK=daily` kullanır: her gün ayrı dosya (`storage/logs/laravel-YYYY-MM-DD.log`), son `LOG_DAILY_DAYS` gün saklanır. Yerel `.env` içinde `single` kalabilir.
