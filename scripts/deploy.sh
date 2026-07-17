#!/usr/bin/env bash
set -Eeuo pipefail

mode="deploy"
if [[ "${1:-}" == "--validate" || "${1:-}" == "--dry-run" ]]; then
    mode="${1#--}"
elif [[ -n "${1:-}" ]]; then
    echo "Usage: $0 [--validate|--dry-run]" >&2
    exit 2
fi

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

env_file="${ENV_FILE:-.env.production}"
export PRODUCTION_ENV_FILE="$(realpath "$env_file")"
compose=(
    docker compose
    --env-file "$env_file"
    -f compose.yaml
    -f compose.production.yaml
)
if [[ -n "${COMPOSE_EXTRA_FILE:-}" ]]; then
    compose+=(-f "$COMPOSE_EXTRA_FILE")
fi

for command in docker git; do
    command -v "$command" >/dev/null || {
        echo "Required command is unavailable: $command" >&2
        exit 1
    }
done
docker compose version >/dev/null
scripts/validate-production-env.sh "$env_file"

root_password_file="$(
    awk -F= '$1 == "MYSQL_ROOT_PASSWORD_FILE" {sub(/^[^=]*=/, ""); print; exit}' "$env_file"
)"
root_password_file="${root_password_file:-./secrets/mysql_root_password}"
if [[ ! -s "$root_password_file" ]]; then
    echo "MySQL root password file is missing or empty: $root_password_file" >&2
    exit 1
fi

"${compose[@]}" config --quiet

if [[ "$mode" == "validate" ]]; then
    echo "Production environment and Compose configuration are valid."
    exit 0
fi

if [[ "$mode" == "dry-run" ]]; then
    cat <<'EOF'
Validation passed. A deployment would build immutable application/web images,
start MySQL, enable maintenance mode, run forward migrations, generate Laravel
caches, restart web/queue/scheduler services, and verify readiness.
EOF
    exit 0
fi

maintenance_enabled=0
leave_maintenance_mode() {
    if [[ "$maintenance_enabled" -eq 1 ]]; then
        "${compose[@]}" run --rm --no-deps app php artisan up >/dev/null || true
    fi
}
trap leave_maintenance_mode EXIT

revision="$(git rev-parse --short=12 HEAD)"
echo "Deploying revision $revision"

"${compose[@]}" build app web
"${compose[@]}" up -d mysql

echo "Waiting for MySQL health..."
for attempt in {1..60}; do
    container_id="$("${compose[@]}" ps -q mysql)"
    health="$(docker inspect --format '{{.State.Health.Status}}' "$container_id" 2>/dev/null || true)"
    [[ "$health" == "healthy" ]] && break
    if [[ "$attempt" -eq 60 ]]; then
        echo "MySQL did not become healthy. Inspect: ${compose[*]} logs mysql" >&2
        exit 1
    fi
    sleep 2
done

"${compose[@]}" run --rm --no-deps app php artisan down --retry=60
maintenance_enabled=1

# platform:diagnose rejects pending migrations by design, so the pre-migration
# gate uses the non-mutating migration status and production environment check.
migration_status="$(mktemp)"
if ! "${compose[@]}" run --rm --no-deps app php artisan migrate:status 2>&1 | tee "$migration_status"; then
    if ! grep -q 'Migration table not found' "$migration_status"; then
        rm -f "$migration_status"
        echo "Pre-migration status failed unexpectedly." >&2
        exit 1
    fi
    echo "First deployment detected: the migration repository will be created."
fi
rm -f "$migration_status"
"${compose[@]}" run --rm --no-deps app php artisan migrate --force --no-interaction
"${compose[@]}" run --rm --no-deps app php artisan storage:link --relative --force
"${compose[@]}" run --rm --no-deps app php artisan optimize:clear
"${compose[@]}" run --rm --no-deps app php artisan config:cache
"${compose[@]}" run --rm --no-deps app php artisan route:cache
"${compose[@]}" run --rm --no-deps app php artisan view:cache
"${compose[@]}" run --rm --no-deps app php artisan platform:diagnose

"${compose[@]}" run --rm --no-deps app php artisan queue:restart
"${compose[@]}" run --rm --no-deps app php artisan schedule:interrupt || true
"${compose[@]}" up -d --force-recreate app queue scheduler

echo "Waiting for application readiness..."
for attempt in {1..60}; do
    container_id="$("${compose[@]}" ps -q app)"
    health="$(docker inspect --format '{{.State.Health.Status}}' "$container_id" 2>/dev/null || true)"
    [[ "$health" == "healthy" ]] && break
    if [[ "$attempt" -eq 60 ]]; then
        echo "Application did not become ready. Maintenance mode remains enabled." >&2
        maintenance_enabled=0
        exit 1
    fi
    sleep 2
done

"${compose[@]}" run --rm --no-deps app php artisan up
maintenance_enabled=0
"${compose[@]}" up -d --force-recreate web

for attempt in {1..30}; do
    container_id="$("${compose[@]}" ps -q web)"
    health="$(docker inspect --format '{{.State.Health.Status}}' "$container_id" 2>/dev/null || true)"
    [[ "$health" == "healthy" ]] && break
    if [[ "$attempt" -eq 30 ]]; then
        echo "Reverse proxy did not become healthy. Inspect web and app logs." >&2
        exit 1
    fi
    sleep 2
done

"${compose[@]}" exec -T app php artisan platform:diagnose
echo "Deployment $revision is ready."
