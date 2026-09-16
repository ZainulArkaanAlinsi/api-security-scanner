# syntax=docker/dockerfile:1

# ---------- 1. Frontend assets ----------
FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
RUN npm ci
COPY resources ./resources
RUN npm run build

# ---------- 2. Application ----------
FROM dunglas/frankenphp:1-php8.3

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
