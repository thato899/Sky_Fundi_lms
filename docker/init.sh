#!/bin/sh
set -eu

if [ ! -f .env ]; then
    cp .env.example .env
    echo "Created .env from .env.example. It remains local and is ignored by Git."
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R ug+rw storage bootstrap/cache

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist --no-progress
fi

# Laravel's dotenv format is shell-compatible for the supplied development
# template. Exporting it lets the bounded MySQL readiness probe use the same
# settings as Laravel without printing credentials.
set -a
. ./.env
set +a

attempt=1
max_attempts=60
until php -r 'new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' >/dev/null 2>&1; do
    if [ "$attempt" -ge "$max_attempts" ]; then
        echo "MySQL did not become ready after $max_attempts attempts." >&2
        exit 1
    fi
    echo "Waiting for MySQL ($attempt/$max_attempts)..."
    attempt=$((attempt + 1))
    sleep 2
done

if ! grep -q '^APP_KEY=.+' .env; then
    php artisan key:generate --force --no-interaction
fi

touch storage/framework/.skyfundi-initialized
echo "Sky Fundi initialization completed."
