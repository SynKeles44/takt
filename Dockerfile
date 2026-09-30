# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Takt — the web half, containerised.
#
# What this image is for: the app runs on a machine that has nothing installed
# but Docker. What it deliberately cannot carry is the native macOS shell —
# the menu bar clock, the global hotkey, calendar reading and away detection
# are Cocoa and EventKit, and a Linux container has neither. Those stay a
# `php artisan takt:app` build on a Mac; everything else is here.
#
# FrankenPHP rather than `artisan serve`: the development area makes requests
# that take tens of seconds (GitHub reviews, two package registries), and a
# single-threaded server turns one slow request into a frozen application.
# ---------------------------------------------------------------------------

# --- assets -----------------------------------------------------------------
FROM node:24-alpine AS assets

WORKDIR /build

COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# --- vendor -----------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /build

COPY composer.json composer.lock ./
# no scripts and no autoloader yet: artisan is not in the context at this layer
RUN composer install \
      --no-dev --no-scripts --no-autoloader \
      --prefer-dist --no-interaction --no-progress

# --- runtime ----------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.4-alpine AS runtime

# git, make, composer and npm are not conveniences here — the development area
# reads repositories, runs make targets and updates packages, and every one of
# those runs inside this container.
RUN apk add --no-cache git make bash nodejs npm tzdata icu-data icu-libs \
 && install-php-extensions pdo_sqlite intl zip opcache pcntl \
 && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ENV TZ=Europe/Berlin \
    APP_ENV=production \
    APP_DEBUG=false \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/takt.sqlite \
    SERVER_NAME=:8000

WORKDIR /app

COPY --from=vendor /build/vendor ./vendor
COPY . .
COPY --from=assets /build/public/build ./public/build

# the autoloader is generated now that the full source is present
RUN composer dump-autoload --optimize --no-dev --no-interaction \
 && mkdir -p /data storage/app/runs storage/framework/{cache/data,sessions,views} storage/logs \
 && chmod -R 777 storage bootstrap/cache /data

COPY docker/entrypoint.sh /usr/local/bin/takt-entrypoint
RUN chmod +x /usr/local/bin/takt-entrypoint

EXPOSE 8000

# a container whose app answers is healthy; a container whose PHP died is not
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8000/up || exit 1

ENTRYPOINT ["takt-entrypoint"]
CMD ["frankenphp", "php-server", "--root", "/app/public", "--listen", ":8000"]
