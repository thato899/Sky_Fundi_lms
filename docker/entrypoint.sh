#!/bin/sh
set -eu
[ -f .env ] || cp .env.example .env
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R ug+rw storage bootstrap/cache
[ -f vendor/autoload.php ] || composer install --no-interaction --prefer-dist
[ -n "$(grep '^APP_KEY=' .env | cut -d= -f2-)" ] || php artisan key:generate --force
exec "$@"
