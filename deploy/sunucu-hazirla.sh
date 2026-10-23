#!/usr/bin/env bash
# Yapboz Kırtasiye — sunucu hazırlığı (Ubuntu 24.04, root olarak). Her yayında yayinla.sh tarafından çalıştırılır;
# yalnızca eksik olanı kurar, var olan ayarlara (ör. certbot'un değiştirdiği Nginx dosyasına, .env'e) dokunmaz.
# Kullanım: bash sunucu-hazirla.sh <alan-adı>     (IDN alan adı punycode yazılır: xn--...)
set -euo pipefail

DOMAIN="${1:?alan adı gerekli}"
BASE=/var/www/yapboz
HERE="$(cd "$(dirname "$0")" && pwd)"

mkdir -p "$BASE/releases" "$BASE/shared"
for d in app/private app/public framework/cache/data framework/sessions framework/views logs; do
    mkdir -p "$BASE/shared/storage/$d"
done

# .env: ilk kurulumda üretilir. Anahtarlar burada rastgele oluşturulur ve yalnızca sunucuda durur.
ENV="$BASE/shared/.env"
if [ ! -f "$ENV" ]; then
    DBPASS="$(cat /root/.yapboz-db-pass)"
    cp "$HERE/../.env.example" "$ENV"
    setenv() {
        if grep -q "^$1=" "$ENV"; then sed -i "s|^$1=.*|$1=$2|" "$ENV"; else echo "$1=$2" >> "$ENV"; fi
    }
    setenv APP_NAME '"Yapboz Kırtasiye"'
    setenv APP_ENV production
    setenv APP_DEBUG false
    setenv APP_URL "https://b2b.$DOMAIN"
    setenv APP_KEY "base64:$(openssl rand -base64 32)"
    setenv LOG_LEVEL warning
    setenv DB_DATABASE yapboz
    setenv DB_USERNAME yapboz
    setenv DB_PASSWORD "$DBPASS"
    setenv SESSION_SECURE_COOKIE true
    setenv MAIL_SECRET_KEY "base64:$(openssl rand -base64 32)"
    setenv STORE_DOMAIN "$DOMAIN"
    setenv STORE_ADMIN_DOMAIN "yonetim.$DOMAIN"
    setenv BACKUP_MYSQLDUMP mysqldump
    chmod 640 "$ENV"
    echo ".env oluşturuldu. APP_KEY ve MAIL_SECRET_KEY'i güvenli bir yere yedekleyin."
fi
chown -R www-data:www-data "$BASE/shared"
chown root:www-data "$ENV"

# PHP
for sapi in fpm cli; do
    install -m 644 "$HERE/php.ini" "/etc/php/8.3/$sapi/conf.d/99-yapboz.ini"
done

# Nginx: yalnızca ilk kurulumda yazılır (sonra certbot HTTPS satırlarını ekler).
SITE=/etc/nginx/sites-available/yapboz
if [ ! -f "$SITE" ]; then
    sed "s/__DOMAIN__/$DOMAIN/g" "$HERE/nginx.conf" > "$SITE"
    ln -sf "$SITE" /etc/nginx/sites-enabled/yapboz
    rm -f /etc/nginx/sites-enabled/default
fi
sed -i 's/^\(\s*\)# server_tokens off;/\1server_tokens off;/' /etc/nginx/nginx.conf

# Kuyruk işçisi ve zamanlayıcı
install -m 644 "$HERE/supervisor.conf" /etc/supervisor/conf.d/yapboz-queue.conf
cat > /etc/cron.d/yapboz <<'CRON'
# Yapboz Kırtasiye — Laravel zamanlayıcısı (gece yedeği, süresi dolan ödemeler, havale süreleri, cari hatırlatmaları)
* * * * * www-data cd /var/www/yapboz/current && php artisan schedule:run >> /dev/null 2>&1
CRON
chmod 644 /etc/cron.d/yapboz
