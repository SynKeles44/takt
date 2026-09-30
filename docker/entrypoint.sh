#!/bin/sh
set -e

# ---------------------------------------------------------------------------
# Everything a fresh machine needs before the first request, and nothing that
# would destroy state on the second start. Each step is idempotent on purpose:
# this runs on every container start, not only the first.
# ---------------------------------------------------------------------------

: "${DB_DATABASE:=/data/takt.sqlite}"

# The key lives in the volume, not in the image. Baking one in would give every
# copy of this image the same key, which is how encrypted values stop being
# encrypted. Generated once, reused ever after.
if [ -z "${APP_KEY:-}" ]; then
    if [ -f /data/app-key ]; then
        APP_KEY="$(cat /data/app-key)"
    else
        APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
        printf '%s' "$APP_KEY" > /data/app-key
        chmod 600 /data/app-key
    fi

    export APP_KEY
fi

# SQLite needs the file to exist before Laravel will touch it
if [ ! -f "$DB_DATABASE" ]; then
    mkdir -p "$(dirname "$DB_DATABASE")"
    : > "$DB_DATABASE"
fi

php artisan migrate --force --no-interaction

# config and routes are cached for speed; views are not, because a stale view
# cache after a rebuild is a blank page with no error
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction

php artisan storage:link --no-interaction 2>/dev/null || true

exec "$@"
