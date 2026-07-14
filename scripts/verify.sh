#!/usr/bin/env bash

set -Eeuo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

docker compose exec -T -e COMPOSER_ROOT_VERSION=dev-main app composer validate --strict
./scripts/migrate-check.sh
docker compose exec -T app php artisan test
docker compose exec -T app ./vendor/bin/pint --test
./scripts/analyse.sh
git diff --check

echo "Verification completed successfully."
