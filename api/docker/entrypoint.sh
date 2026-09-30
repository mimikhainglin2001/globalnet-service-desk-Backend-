#!/bin/sh
set -e

cd /var/www/html

# Local Docker convenience: generate one APP_KEY and share it with the queue
# and scheduler containers through the storage volume. Production must pass
# APP_KEY as a secret environment variable instead.
if [ -z "$APP_KEY" ]; then
    KEY_FILE=storage/app/.docker-app-key
    if [ ! -s "$KEY_FILE" ]; then
        php artisan key:generate --show --no-ansi > "$KEY_FILE"
    fi
    export APP_KEY="$(cat "$KEY_FILE")"
fi

php artisan package:discover --ansi >/dev/null

if [ -n "$DB_HOST" ]; then
    echo "Waiting for database at $DB_HOST:${DB_PORT:-3306}..."
    until php -r 'new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: 3306), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' >/dev/null 2>&1; do
        sleep 2
    done
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
    if [ "${RUN_SEEDERS:-false}" = "true" ]; then
        php artisan db:seed --force
    fi
fi

php artisan config:cache
php artisan route:cache
php artisan event:cache

exec "$@"
