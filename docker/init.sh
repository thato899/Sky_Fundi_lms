#!/bin/sh
set -eu

if [ ! -f .env ]; then
    cp .env.example .env
    echo "Created .env from .env.example. It remains local and is ignored by Git."
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R ug+rw storage bootstrap/cache

git config --global --add safe.directory /var/www/html || true

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist --no-progress
fi

attempt=1
max_attempts=60
until php artisan db:show --no-interaction >/dev/null 2>&1; do
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

php artisan migrate --force --no-interaction
php artisan storage:link --relative --force

if [ "${DEMO_MODE:-false}" = "true" ]; then
    php artisan db:seed --force
    php artisan db:seed --force --class="Database\\Seeders\\HackathonDemoSeeder"
else
    php artisan db:seed --force
fi

php artisan platform:validate-environment
touch storage/framework/.skyfundi-initialized
echo "Sky Fundi initialization completed."
