#!/usr/bin/env bash

set -Eeuo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

echo "Repository"
printf 'Path:   %s\n' "$(pwd)"
printf 'Branch: %s\n' "$(git branch --show-current)"
git status --short

echo
echo "Docker Compose"
docker compose ps

echo
echo "Migrations"
docker compose exec -T app php artisan migrate:status
