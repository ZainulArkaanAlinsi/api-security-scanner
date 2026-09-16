#!/usr/bin/env bash
# One-shot setup for a fresh Ubuntu VM (tested shape: Oracle Cloud Always Free,
# Ampere A1 / arm64, Ubuntu 22.04 or 24.04).
#
#   curl -fsSL https://raw.githubusercontent.com/ZainulArkaanAlinsi/api-security-scanner/main/deploy/setup-oracle.sh -o setup.sh
#   less setup.sh            # read it before running anything from the internet
#   bash setup.sh            # optionally: DOMAIN=scanner.duckdns.org bash setup.sh
#
# Re-running is safe: it never overwrites an existing .env.

set -euo pipefail

REPO="${REPO:-https://github.com/ZainulArkaanAlinsi/api-security-scanner.git}"
APP_DIR="${APP_DIR:-$HOME/api-security-scanner}"
DOMAIN="${DOMAIN:-}"

say() { printf '\n\033[1m==> %s\033[0m\n' "$1"; }

# ---------------------------------------------------------------- Docker
if ! command -v docker >/dev/null 2>&1; then
  say "Memasang Docker"
  curl -fsSL https://get.docker.com | sh
  sudo usermod -aG docker "$USER"
  NEED_RELOGIN=1
fi

# ---------------------------------------------------------------- Firewall
# Oracle images ship with a default REJECT rule that blocks everything but SSH.
say "Membuka port 80 dan 443 di firewall VM"
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT || true
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT || true
sudo netfilter-persistent save >/dev/null 2>&1 || sudo apt-get install -y iptables-persistent >/dev/null 2>&1 || true

echo "Ingat: port 80/443 juga harus dibuka di Security List / NSG lewat konsol Oracle."

# ---------------------------------------------------------------- Source
if [ -d "$APP_DIR/.git" ]; then
  say "Memperbarui kode"
  git -C "$APP_DIR" pull --ff-only
else
  say "Mengunduh kode"
  sudo apt-get update -qq && sudo apt-get install -y git >/dev/null
  git clone "$REPO" "$APP_DIR"
fi

cd "$APP_DIR"

# ---------------------------------------------------------------- .env
if [ ! -f .env ]; then
  say "Membuat .env"
  cp .env.example .env

  DB_PASS="$(head -c 32 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 24)"
  APP_URL_VALUE="http://$(curl -fsS --max-time 5 ifconfig.me || echo localhost)"
  SERVER_NAME_VALUE=":80"

  if [ -n "$DOMAIN" ]; then
    APP_URL_VALUE="https://${DOMAIN}"
    SERVER_NAME_VALUE="${DOMAIN}"   # FrankenPHP requests a Let's Encrypt cert itself
  fi

  set_env() { sed -i "s|^#\?${1}=.*|${1}=${2}|" .env; }

  set_env APP_NAME '"API Scanner"'
  set_env APP_ENV production
  set_env APP_DEBUG false
  set_env APP_URL "$APP_URL_VALUE"
  set_env APP_LOCALE id
  set_env APP_TIMEZONE Asia/Jakarta
  set_env LOG_CHANNEL stderr
  set_env DB_CONNECTION mysql
  set_env DB_HOST db
  set_env DB_PORT 3306
  set_env DB_DATABASE api_scanner
  set_env DB_USERNAME api_scanner
  set_env DB_PASSWORD "$DB_PASS"
  set_env SESSION_DRIVER database
  set_env QUEUE_CONNECTION database
  set_env CACHE_STORE database
  set_env SCANNER_ALLOW_PRIVATE false

  [ -n "$DOMAIN" ] && set_env SESSION_SECURE_COOKIE true

  printf '\nSERVER_NAME=%s\n' "$SERVER_NAME_VALUE" >> .env
else
  say ".env sudah ada, dibiarkan apa adanya"
fi

# ---------------------------------------------------------------- Start
say "Membangun image dan menjalankan stack (butuh beberapa menit pada build pertama)"
docker compose -f deploy/docker-compose.prod.yml up -d --build

say "Menyiapkan aplikasi"
docker compose -f deploy/docker-compose.prod.yml exec -T app php artisan key:generate --force
docker compose -f deploy/docker-compose.prod.yml exec -T app php artisan migrate --force
docker compose -f deploy/docker-compose.prod.yml exec -T app php artisan config:cache
docker compose -f deploy/docker-compose.prod.yml exec -T app php artisan route:cache

say "Selesai"
docker compose -f deploy/docker-compose.prod.yml ps

if [ -n "$DOMAIN" ]; then
  echo "Buka: https://${DOMAIN}"
else
  echo "Buka: http://$(curl -fsS --max-time 5 ifconfig.me || echo '<ip-vm>')"
fi

echo
echo "Buat akun pertama lewat halaman Daftar. Jangan jalankan DemoSeeder di server publik."
[ "${NEED_RELOGIN:-0}" = "1" ] && echo "Catatan: logout lalu login lagi supaya docker bisa dipakai tanpa sudo."
