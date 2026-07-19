#!/usr/bin/env bash
set -Eeuo pipefail

if [[ "${1:-}" != "--confirm-production" && "${CPANEL_DEPLOY_CONFIRM:-}" != "production" ]]; then
    echo "Production confirmation required." >&2
    echo "Run: $0 --confirm-production" >&2
    exit 2
fi

if [[ -n "${2:-}" ]]; then
    echo "Usage: $0 --confirm-production" >&2
    exit 2
fi

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

if [[ ! -f artisan || ! -f composer.json || ! -d bootstrap || ! -d public ]]; then
    echo "Run this script from a complete Sky Fundi repository checkout." >&2
    exit 1
fi

for command in php composer; do
    command -v "$command" >/dev/null || {
        echo "Required command is unavailable: $command" >&2
        exit 1
    }
done

if [[ ! -f .env ]]; then
    echo "The repository .env symlink/file is missing." >&2
    echo "Create it from the private shared environment file before deployment." >&2
    exit 1
fi

env_mode="$(stat -L -c '%a' .env 2>/dev/null || stat -L -f '%Lp' .env)"
if [[ ! "$env_mode" =~ [0-7]00$ ]]; then
    echo "The environment file is readable by other users; restrict it to mode 600." >&2
    exit 1
fi

shared_root="${SKY_FUNDI_SHARED_ROOT:-$HOME/sky-fundi-shared}"
shared_storage="$shared_root/storage"
mkdir -p "$shared_root"

if [[ ! -L storage ]]; then
    if [[ -e "$shared_storage" ]]; then
        echo "Refusing to replace storage because both repository and shared storage exist." >&2
        echo "Reconcile them, then link storage to $shared_storage." >&2
        exit 1
    fi

    mv storage "$shared_storage"
    ln -s "$shared_storage" storage
fi

if [[ ! -d storage || ! -w storage ]]; then
    echo "Persistent storage is unavailable or not writable." >&2
    exit 1
fi

maintenance_enabled=0
restore_service() {
    if [[ "$maintenance_enabled" -eq 1 && -f vendor/autoload.php ]]; then
        php artisan up >/dev/null 2>&1 || true
    fi
}
trap restore_service EXIT

if [[ -f vendor/autoload.php ]]; then
    php artisan down --retry=60
    maintenance_enabled=1
fi

composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/app/temp \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

find storage bootstrap/cache -type d -exec chmod 775 {} +
find storage bootstrap/cache -type f -exec chmod 664 {} +

php artisan storage:link --relative --force
php artisan optimize:clear
php artisan platform:validate-environment
php artisan migrate --force --no-interaction
php artisan platform:validate-environment --cpanel
php artisan optimize
php artisan queue:restart
php artisan platform:diagnose

php artisan up
maintenance_enabled=0

echo "Sky Fundi cPanel deployment completed successfully."
