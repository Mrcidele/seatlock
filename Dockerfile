# syntax=docker/dockerfile:1.7

############################
# Base: PHP 8.4 + FrankenPHP
############################
FROM dunglas/frankenphp:1-php8.4 AS base

RUN install-php-extensions pdo_pgsql pgsql redis pcntl intl zip gd bcmath opcache sockets @composer \
    && apt-get update \
    && apt-get install -y --no-install-recommends git unzip postgresql-client \
    && rm -rf /var/lib/apt/lists/*

ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app

############################
# Dev: código montado por volume
############################
FROM base AS dev

COPY docker/php/dev.ini $PHP_INI_DIR/conf.d/zz-app.ini

CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8000"]

############################
# Dependências PHP de produção
############################
FROM base AS vendor

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

############################
# Build do frontend
############################
FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY vite.config.ts tsconfig.json ./
COPY public ./public
RUN npm run build

############################
# Produção (Octane + FrankenPHP)
############################
FROM base AS production

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_STACK=json \
    OCTANE_SERVER=frankenphp

COPY docker/php/prod.ini $PHP_INI_DIR/conf.d/zz-app.ini
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 8000
HEALTHCHECK --interval=10s --timeout=3s --retries=5 CMD curl -fsS http://127.0.0.1:8000/up || exit 1

CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8000", "--workers=auto", "--max-requests=1000"]
