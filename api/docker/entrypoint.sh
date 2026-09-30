#!/bin/sh
set -e

cd /var/www/html

# Hosts that run one image as several services (e.g. Railway) pick the role
# with an environment variable instead of a command override.
case "${CONTAINER_ROLE:-}" in
    web) set -- serve-web ;;
    queue) set -- php artisan queue:work --tries=5 --backoff=10 --max-time=3600 ;;
    scheduler) set -- php artisan schedule:work ;;
    "") ;;
    *) echo "Unknown CONTAINER_ROLE '$CONTAINER_ROLE' (expected web, queue or scheduler)." >&2; exit 1 ;;
esac

# Local Docker convenience: generate one APP_KEY and share it with the queue
# and scheduler containers through the storage volume. Production must pass
# APP_KEY as a secret environment variable instead, because separate services
# do not share a filesystem and a generated key would differ per container.
if [ -z "$APP_KEY" ]; then
    if [ "$APP_ENV" = "production" ]; then
        echo "APP_KEY is not set. Generate one with 'php artisan key:generate --show' and add it to the environment." >&2
        exit 1
    fi
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

if [ "$1" = "serve-web" ]; then
    sed "s/__PORT__/${PORT:-8080}/g" /etc/nginx/single-container.conf > /tmp/nginx.conf
    php-fpm -D
    exec nginx -e /dev/stderr -c /tmp/nginx.conf -g 'daemon off;'
fi

exec "$@"
