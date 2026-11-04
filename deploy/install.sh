#!/usr/bin/env bash
# MEDEK Form – http://medekform.loc kurulumu (Apache + /etc/hosts). sudo şifresi ister.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CONF=/etc/apache2/sites-available/medekform.loc.conf

echo "1) Veri klasörü ve hesaplar"
php "$ROOT/bin/init.php"
chmod -R u+rwX,g+rwX "$ROOT/data" || true
sudo chgrp -R www-data "$ROOT/data" 2>/dev/null || true

echo "2) Apache sanal sunucusu"
sed "s|__ROOT__|$ROOT|g" "$ROOT/deploy/medekform.loc.conf.tpl" | sudo tee "$CONF" >/dev/null
sudo a2ensite medekform.loc.conf

echo "3) /etc/hosts"
grep -qE '(^|\s)medekform\.loc(\s|$)' /etc/hosts || echo "127.0.0.1  medekform.loc" | sudo tee -a /etc/hosts >/dev/null

echo "4) Apache yeniden yükleniyor"
sudo apache2ctl configtest
sudo systemctl reload apache2

echo
echo "Hazır: http://medekform.loc"
echo "İlk şifreler: $ROOT/data/ILK-SIFRELER.txt"
