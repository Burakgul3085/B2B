#!/usr/bin/env bash
# Yapboz Kırtasiye — yayınlama (geliştirme bilgisayarında, Git Bash'te çalışır).
#  1. Ön yüzü derler (sunucuda Node gerekmez).
#  2. Son commit'i (HEAD) + public/build'i paketleyip sunucuya gönderir; commit'lenmemiş değişiklik gönderilmez.
#  3. Sunucuda yeni sürüm klasörüne açar, bağımlılıkları kurar, veritabanını günceller, önbellekleri hazırlar,
#     "current" bağlantısını yeni sürüme çevirir (kesintisiz), PHP'yi ve kuyruk işçisini yeniler. Son 5 sürüm saklanır.
# Kullanım: bash deploy/yayinla.sh            (ayarlar aşağıda; YAPBOZ_HOST ve YAPBOZ_KEY ile değiştirilebilir)
# Geri alma: sunucuda  ln -sfn /var/www/yapboz/releases/<önceki> /var/www/yapboz/current && systemctl reload php8.3-fpm
set -euo pipefail

HOST="${YAPBOZ_HOST:-root@187.126.9.129}"
KEY="${YAPBOZ_KEY:-$HOME/.ssh/yapboz_deploy}"
DOMAIN="${YAPBOZ_DOMAIN:-xn--yapbozkrtasiye-cgc.com}"   # yapbozkırtasiye.com
SSH=(ssh -i "$KEY" -o BatchMode=yes "$HOST")

cd "$(dirname "$0")/.."
if [ -n "$(git status --porcelain)" ]; then
    echo "Uyarı: commit'lenmemiş değişiklikler var; yalnızca son commit ($(git rev-parse --short HEAD)) yayınlanır."
fi

REV="$(git rev-parse --short HEAD)"
STAMP="$(date +%Y%m%d-%H%M%S)-$REV"
PKG="$(mktemp -d)/yapboz-$STAMP.tar"

echo "→ Ön yüz derleniyor"
npm run build --silent >/dev/null

echo "→ Paket hazırlanıyor ($REV)"
git -c core.autocrlf=false archive --format=tar -o "$PKG" HEAD
tar -rf "$PKG" public/build
gzip -9 "$PKG"

echo "→ Sunucuya gönderiliyor"
scp -q -i "$KEY" -o BatchMode=yes "$PKG.gz" "$HOST:/tmp/yapboz-$STAMP.tar.gz"
rm -f "$PKG.gz"

echo "→ Sunucuda kuruluyor"
"${SSH[@]}" bash -s -- "$STAMP" "$DOMAIN" <<'REMOTE'
set -euo pipefail
STAMP="$1"; DOMAIN="$2"
BASE=/var/www/yapboz
REL="$BASE/releases/$STAMP"
mkdir -p "$REL"
tar -xzf "/tmp/yapboz-$STAMP.tar.gz" -C "$REL"
rm -f "/tmp/yapboz-$STAMP.tar.gz"

bash "$REL/deploy/sunucu-hazirla.sh" "$DOMAIN"

rm -rf "$REL/storage"
ln -s "$BASE/shared/storage" "$REL/storage"
ln -s "$BASE/shared/.env" "$REL/.env"

cd "$REL"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet
chown -R www-data:www-data "$REL"

as_www() { sudo -u www-data php "$REL/artisan" "$@"; }
as_www migrate --force
as_www config:cache >/dev/null
as_www route:cache >/dev/null
as_www view:cache >/dev/null
as_www event:cache >/dev/null

# Kesintisiz geçiş: bağlantı tek adımda değişir.
ln -sfn "$REL" "$BASE/current.new" && mv -T "$BASE/current.new" "$BASE/current"

systemctl reload php8.3-fpm
nginx -t -q && systemctl reload nginx
supervisorctl reread >/dev/null && supervisorctl update >/dev/null
as_www queue:restart >/dev/null

# Son 5 sürüm kalır.
ls -1dt "$BASE"/releases/* | tail -n +6 | xargs -r rm -rf
echo "Yayında: $STAMP"
REMOTE
