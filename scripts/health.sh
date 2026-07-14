#!/usr/bin/env bash

set -Eeuo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

docker compose ps
curl --fail --silent --show-error --max-time 10 http://localhost:8000/up >/dev/null
docker compose exec -T app php artisan about --only=environment

echo "Sky Fundi is healthy at http://localhost:8000/up."
