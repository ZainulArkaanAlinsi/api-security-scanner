# syntax=docker/dockerfile:1

# ---------- 1. Frontend assets ----------
FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
RUN npm ci
COPY resources ./resources
RUN npm run build

# ---------- 2. Application ----------
# Pinned to the patched release (2026-10-22): PHP 8.3.36 fixes CVE-2026-109618 and
# CVE-2026-109619 (unauthenticated RCE, php.net 2026-10-21). Bump deliberately.
FROM dunglas/frankenphp:1.13.2-php8.3.36

# Fail the build if the image ever carries an unpatched PHP.
RUN php -r 'exit(version_compare(PHP_VERSION, "8.3.36", "<") ? 1 : 0);'

# pcntl is what lets the queue worker handle restart/timeout signals.
RUN install-php-extensions pdo_mysql pdo_pgsql pdo_sqlite intl zip opcache pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

ENV SERVER_NAME=":8080"
EXPOSE 8080

# Web process. The queue worker and scheduler run the same image with a
# different command — see fly.toml, docker-compose.yml and docs/DEPLOY.md.
CMD ["frankenphp", "php-server", "--root", "/app/public", "--listen", ":8080"]
