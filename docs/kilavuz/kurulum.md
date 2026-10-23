# Sunucu kurulum kılavuzu

Tek sunucu: Hostinger KVM 2, Ubuntu 24.04, Nginx, PHP 8.5-FPM, MySQL 8.0. Yerel geliştirme kurulumu [../GELISTIRME.md](../GELISTIRME.md) dosyasındadır.

## 0. Canlı sunucu (23.10.2026'da kuruldu)

| | |
|---|---|
| Sunucu | `187.126.9.129` (srv2087766). Bağlantı `root`, bu bilgisayardaki `~/.ssh/yapboz_deploy` anahtarıyla. |
| Alan adı | **yapbozkırtasiye.com**. ı harfi içerdiği için teknik adı `xn--yapbozkrtasiye-cgc.com`. Tarayıcıya her iki yazım da girilebilir. |
| Mağaza | https://yapbozkırtasiye.com (www, çıplak alan adına yönlenir) |
| Mağaza yönetimi | https://yonetim.yapbozkırtasiye.com |
| B2B | https://b2b.yapbozkırtasiye.com/bayi · /toptanci · /platform |
| Uygulama | `/var/www/yapboz/current`, bir sürüm klasörüne bağlantıdır (`releases/<tarih>-<commit>`). Ortak dosyalar `shared/.env` ve `shared/storage` içindedir. |
| Veritabanı | MySQL `yapboz`, kullanıcı `yapboz`. Şifresi sunucuda `/root/.yapboz-db-pass` dosyasında ve `.env` içinde. |
| HTTPS | Let's Encrypt (certbot). Sertifika kendiliğinden yenilenir (`certbot.timer`). |
| Güvenlik duvarı | ufw: yalnızca 22, 80 ve 443 açık. |

**Güncelleme (yayınlama):** Kod commit'lendikten sonra geliştirme bilgisayarında şu komut çalıştırılır:

```bash
bash deploy/yayinla.sh
```

Betik şunları yapar:

1. Ön yüzü derler.
2. Son commit'i paketleyip sunucuya gönderir.
3. Yeni sürüm klasörüne kurar. Veritabanını günceller (`migrate`) ve önbellekleri hazırlar.
4. `current` bağlantısını tek adımda yeni sürüme çevirir, böylece kesinti olmaz.
5. Kuyruk işçisini yeniler.

Son 5 sürüm saklanır. Bir önceki sürüme dönmek için sunucuda şu komut çalıştırılır:

```bash
ln -sfn /var/www/yapboz/releases/<önceki> /var/www/yapboz/current && systemctl reload php8.5-fpm
```

Veritabanı değişiklikleri bu şekilde geri alınmaz.

Sunucu ayarlarının kaynağı `deploy/` klasörüdür: `nginx.conf`, `php.ini`, `supervisor.conf`, `sunucu-hazirla.sh`. Aşağıdaki bölümler aynı kurulumu elle yapmak için başvuru niteliğindedir.

## 1. Alan adları

Üç adres de aynı uygulamaya (`public/` klasörü) yönlenir:

| Adres | Ne açar | `.env` |
|---|---|---|
| `b2b.yapbozkirtasiye.com` (örnek) | B2B: `/bayi`, `/toptanci`, `/platform` | `APP_URL` |
| `yapbozkirtasiye.com` | Mağaza | `STORE_DOMAIN` |
| `yonetim.yapbozkirtasiye.com` | Mağaza yönetimi | `STORE_ADMIN_DOMAIN` |

B2B adresleri mağaza alan adlarında açılmaz; mağaza da B2B adresinde açılmaz. Üçü için de HTTPS sertifikası alın (ör. Let's Encrypt / certbot).

## 2. Gerekenler

- PHP **8.4 veya üstü**. Laravel 13 bağımlılıkları bunu istiyor; Ubuntu 24.04'ün kendi PHP'si 8.3 olduğu için `ppa:ondrej/php` deposundan 8.5 kurulur. Gereken eklentiler: `bcmath`, `gd` (JPEG/PNG/WebP), `intl`, `mbstring`, `mysql`, `zip`, `xml`, `curl`.
- MySQL 8.4. Ayrıca `mysqldump` kurulu olmalı; yedekleme bunu kullanır.
- Composer ve Node.js. Node yalnızca ön yüzü derlemek için gerekir.
- `php.ini`: `upload_max_filesize = 64M`, `post_max_size = 64M` (vitrin videoları için).
- Nginx: `client_max_body_size 64m;`

## 3. Uygulama

```bash
git clone <depo> /var/www/b2b && cd /var/www/b2b
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env
php artisan key:generate
```

`.env` dosyasında doldurulacaklar (`.env.example` içindeki açıklamalara bakın):

- **Uygulama:** `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`
- **Veritabanı:** `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. Uygulama için ayrı bir MySQL kullanıcısı açın.
- **Şifreleme anahtarı:** `MAIL_SECRET_KEY`. Gmail uygulama şifrelerini şifreler ve `APP_KEY`'den ayrıdır; **kaybedilirse kayıtlı şifreler çözülemez**. Bu yüzden yedekleyin. Üretmek için:

  ```bash
  php -r "echo 'base64:'.base64_encode(random_bytes(32));"
  ```

- **Alan adları:** `STORE_DOMAIN`, `STORE_ADMIN_DOMAIN`
- **Sistem e-postası:** `SYSTEM_MAIL_*`. Mağaza e-postaları ve platform şifre sıfırlaması bu hesaptan gider; işlem e-postası servisi önerilir.
- **Yedekleme ve günlük:** `BACKUP_KEEP`; `LOG_STACK=daily`

Ardından:

```bash
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan platform:create-admin
```

`storage/` ve `bootstrap/cache/` klasörlerine web sunucusu kullanıcısının (`www-data`) yazma izni olmalı.

## 4. Arka plan işleri

**Zamanlayıcı** (cron, her dakika). Gece yedeği (03:30), 5 dakikada bir süresi dolan kart ödemeleri, saatte bir ödenmeyen havale siparişleri ve pazartesi sabahı bayilere cari hatırlatma e-postası bununla çalışır:

```cron
* * * * * cd /var/www/b2b && php artisan schedule:run >> /dev/null 2>&1
```

**Kuyruk işçisi** (Supervisor ile sürekli). E-postalar bununla gönderilir:

```ini
[program:b2b-queue]
command=php /var/www/b2b/artisan queue:work --tries=3 --sleep=2 --max-time=3600
user=www-data
autostart=true
autorestart=true
stopwaitsecs=60
```

Her güncellemeden sonra şunu çalıştırın; aksi halde işçi eski kodla çalışmaya devam eder:

```bash
php artisan queue:restart
```

## 5. Güncelleme

```bash
cd /var/www/b2b
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
php artisan up
```

## 6. Yedek ve geri yükleme

- Yedekler `storage/app/private/backups` altında tek zip olarak tutulur: veritabanı dökümü ve yüklenen dosyalar. Son `BACKUP_KEEP` yedek saklanır.
- Yedeklerin **sunucu dışına** da kopyalanması gerekir (uzak depolama). Bu henüz ayarlanmadı.
- Geri yükleme:
  1. Zip'i açın.
  2. Veritabanı dökümünü `mysql` ile içe aktarın.
  3. Dosyaları `storage/app/private` altına kopyalayın.
- Yayından önce geri yükleme bir kez sunucuda denenmelidir.

## 7. Yayından önce

- Mağaza yönetiminde **Ayarlar → Yayın Hazırlığı** listesini tamamlayın.
- iyzico bildirim adresini iyzico paneline girin.
- Netgsm bilgilerini girin.
- Performansı sunucuda ölçün:

  ```bash
  php artisan test --group=performance
  ```

  Bu komut `phpunit.xml`'deki ayrı test veritabanında (`b2b_test`) çalışır ve o veritabanını boşaltır. Önce bu veritabanını oluşturun; canlı veritabanında **asla** çalıştırmayın.
- Kalan işlerin listesi: [../YAPILACAKLAR.md](../YAPILACAKLAR.md).
