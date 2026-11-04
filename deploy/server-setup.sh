#!/usr/bin/env bash
# MEDEK Form – Ubuntu + nginx + PHP-FPM sunucu kurulumu (tekrar çalıştırılabilir).
#
# Kullanım (root olarak, depo klasöründen):
#   sudo ./deploy/server-setup.sh medek.gencbilisim.net [letsencrypt-e-posta]
#
# E-posta verilirse HTTPS sertifikası da alınır (DNS A kaydı sunucuya işaret etmeli).
# E-posta verilmezse yalnızca HTTP kurulur; HTTPS için betiği e-postayla tekrar çalıştırın.
set -euo pipefail

DOMAIN="${1:-medek.gencbilisim.net}"
EMAIL="${2:-}"
[ "$(id -u)" -eq 0 ] || { echo "Root olarak çalıştırın: sudo $0 $DOMAIN [e-posta]"; exit 1; }

P="$(cd "$(dirname "$0")/.." && pwd)"
OWNER="$(stat -c %U "$P")"
say() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }

say "1/8 Paketler"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq nginx php-fpm php-cli php-zip php-xml php-mbstring curl certbot python3-certbot-nginx >/dev/null
PHPV="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
SOCK="/run/php/php${PHPV}-fpm.sock"
systemctl enable --now "php${PHPV}-fpm" nginx >/dev/null 2>&1 || true
echo "PHP $PHPV, proje: $P (sahibi: $OWNER)"

say "2/8 Dosya izinleri"
# www-data'nın yol boyunca geçebilmesi için üst dizinlere yalnızca "x" (listeleme yok)
d="$(dirname "$P")"
while [ "$d" != "/" ]; do chmod o+x "$d"; d="$(dirname "$d")"; done
chmod -R o+rX "$P"
chmod -R o-rwx "$P/.git" 2>/dev/null || true       # .git web kullanıcısına kapalı
mkdir -p "$P/data"
chgrp -R www-data "$P/data"
chmod -R u+rwX,g+rwX,o-rwx "$P/data"
chmod g+s "$P/data"

say "3/8 Hesaplar ve başlangıç verisi"
sudo -u "$OWNER" php "$P/bin/init.php"
sudo -u "$OWNER" php "$P/bin/seed.php"
chgrp -R www-data "$P/data"; chmod -R u+rwX,g+rwX,o-rwx "$P/data"

write_ini() {   # $1 = cookie_secure (0|1)
  cat > "/etc/php/${PHPV}/fpm/conf.d/90-medekform.ini" <<EOF
upload_max_filesize = 32M
post_max_size = 40M
max_file_uploads = 20
session.cookie_httponly = 1
session.cookie_secure = $1
session.gc_maxlifetime = 28800
expose_php = 0
EOF
  systemctl restart "php${PHPV}-fpm"
}
SECURE=0; [ -d "/etc/letsencrypt/live/$DOMAIN" ] && SECURE=1

say "4/8 PHP ayarları (cookie_secure=$SECURE)"
write_ini "$SECURE"

say "5/8 nginx"
CONF="/etc/nginx/sites-available/$DOMAIN"
if [ ! -d "/etc/letsencrypt/live/$DOMAIN" ]; then   # certbot kurulmuşsa bloğunu ezmeyelim
cat > "$CONF" <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name __DOMAIN__;

    root __ROOT__/public;
    index index.php;
    client_max_body_size 40M;

    location ~ /\.(?!well-known) { deny all; }

    location / {
        try_files $uri /index.php?$query_string;
    }

    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param HTTP_PROXY "";
        fastcgi_pass unix:__SOCK__;
    }

    # index.php dışında hiçbir PHP dosyası çalıştırılmasın
    location ~ \.php$ { return 404; }

    location ~* \.(css|js)$ { expires 7d; }

    access_log /var/log/nginx/__DOMAIN___access.log;
    error_log  /var/log/nginx/__DOMAIN___error.log;
}
EOF
sed -i "s|__DOMAIN__|$DOMAIN|g; s|__ROOT__|$P|g; s|__SOCK__|$SOCK|g" "$CONF"
else
  echo "HTTPS yapılandırması mevcut; nginx bloğu değiştirilmedi ($CONF)."
fi
rm -f "/etc/nginx/sites-enabled/$DOMAIN"            # eski (uzantısız) bağlantı
ln -sf "$CONF" "/etc/nginx/sites-enabled/$DOMAIN.conf"  # bazı sunucularda yalnızca *.conf dahil edilir
nginx -t
systemctl reload nginx

say "6/8 DNS kontrolü"
IP_DNS="$(getent ahostsv4 "$DOMAIN" | awk '{print $1; exit}' || true)"
IP_ME="$(curl -4 -fsS --max-time 8 https://ifconfig.me 2>/dev/null || true)"
echo "DNS: ${IP_DNS:-yok} · Bu sunucu: ${IP_ME:-bilinmiyor}"
DNS_OK=0; [ -n "$IP_DNS" ] && [ "$IP_DNS" = "$IP_ME" ] && DNS_OK=1
[ "$DNS_OK" -eq 1 ] || echo "UYARI: $DOMAIN şu an bu sunucuya işaret etmiyor; HTTPS alınamayabilir."

say "7/8 HTTPS"
if [ -d "/etc/letsencrypt/live/$DOMAIN" ]; then
  echo "Sertifika zaten var."
elif [ -n "$EMAIL" ] && [ "$DNS_OK" -eq 1 ]; then
  certbot --nginx -d "$DOMAIN" -m "$EMAIL" --agree-tos --no-eff-email --redirect -n
  write_ini 1
else
  echo "Atlandı. DNS hazır olunca: sudo $0 $DOMAIN senin@epostan.com"
fi

say "8/8 Kontrol"
H=(-H "Host: $DOMAIN")
c() { curl -s -o /dev/null -w '%{http_code}' "${H[@]}" "$1"; }
G="$(c http://127.0.0.1/giris)"; U="$(c http://127.0.0.1/data/users.json)"; S="$(c http://127.0.0.1/src/lib.php)"
echo "/giris           : $G  (200 ya da 301 beklenir)"
echo "/data/users.json : $U  (404 beklenir)"
echo "/src/lib.php     : $S  (404 beklenir)"
sudo -u www-data test -w "$P/data" && echo "data/ yazılabilir: evet" || echo "UYARI: www-data data/ içine yazamıyor"
[ "$U" = "404" ] && [ "$S" = "404" ] || echo "UYARI: gizli dosyalar açıkta olabilir, nginx yapılandırmasını kontrol edin!"

cat <<EOF

Bitti. Sıradaki adımlar:
  1) Şifreleri kaydedin:   cat $P/data/ILK-SIFRELER.txt
  2) Adrese girin:         http$([ "$SECURE" = 1 ] || [ -d /etc/letsencrypt/live/$DOMAIN ] && echo s)://$DOMAIN  (yönetici ile giriş → Yönetim → şifreleri değiştirin)
  3) Dosyayı silin:        rm $P/data/ILK-SIFRELER.txt
Hata günlüğü: /var/log/nginx/${DOMAIN}_error.log
EOF
