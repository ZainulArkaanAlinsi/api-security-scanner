#!/usr/bin/env bash
# Runs once when the Codespace (or dev container) is created.
set -euo pipefail

echo "==> composer install"
composer install --no-interaction --prefer-dist

echo "==> frontend assets"
npm ci --silent && npm run build

echo "==> environment"
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate

    # SQLite keeps the demo self-contained: no database service to start.
    sed -i 's|^DB_CONNECTION=.*|DB_CONNECTION=sqlite|' .env
    sed -i 's|^SESSION_DRIVER=.*|SESSION_DRIVER=database|' .env
    sed -i 's|^QUEUE_CONNECTION=.*|QUEUE_CONNECTION=database|' .env
    sed -i 's|^CACHE_STORE=.*|CACHE_STORE=database|' .env
    touch database/database.sqlite
fi

echo "==> database"
php artisan migrate --force
php artisan db:seed --class=DemoSeeder --force

cat <<'EOF'

  Siap. Aplikasi jalan di port 8000 (tab Ports).

  Masuk dengan:
    demo@example.com  /  demo12345

  Queue worker sudah berjalan, jadi scan langsung diproses.
  Jalankan test kapan saja:  php artisan test

EOF
