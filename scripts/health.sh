#!/usr/bin/env bash

set -Eeuo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

env_file="${ENV_FILE:-.env}"
health_host="${HEALTH_HOST:-localhost}"
health_port="${HTTP_PORT:-8000}"
if [[ -r "$env_file" ]]; then
    configured_port="$(awk -F= '$1 == "HTTP_PORT" {sub(/^[^=]*=/, ""); print; exit}' "$env_file")"
    [[ -n "$configured_port" ]] && health_port="$configured_port"
fi

docker compose ps
curl --fail --silent --show-error --max-time 10 "http://${health_host}:${health_port}/up" >/dev/null
docker compose exec -T app php artisan about --only=environment

echo "Sky Fundi is healthy at http://${health_host}:${health_port}/up."
