#!/usr/bin/env bash
set -Eeuo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

command -v curl >/dev/null || {
    echo "curl is required for the local staging simulation." >&2
    exit 1
}

simulation_root="$(mktemp -d /tmp/sky-fundi-staging-test.XXXXXX)"
export STAGING_TEST_ROOT="$simulation_root/state"
export STAGING_TEST_HTTP_PORT="${STAGING_TEST_HTTP_PORT:-18080}"
export ENV_FILE="$simulation_root/.env.production"
export PRODUCTION_ENV_FILE="$ENV_FILE"
export COMPOSE_PROJECT_NAME="sky-fundi-staging-test"
export COMPOSE_EXTRA_FILE="$root/compose.staging-test.yaml"

mkdir -p \
    "$STAGING_TEST_ROOT/mysql" \
    "$STAGING_TEST_ROOT/storage/app/private" \
    "$STAGING_TEST_ROOT/storage/app/public" \
    "$STAGING_TEST_ROOT/storage/app/temp" \
    "$STAGING_TEST_ROOT/storage/framework/cache" \
    "$STAGING_TEST_ROOT/storage/framework/sessions" \
    "$STAGING_TEST_ROOT/storage/framework/views" \
    "$STAGING_TEST_ROOT/storage/logs" \
    "$STAGING_TEST_ROOT/bootstrap-cache" \
    "$STAGING_TEST_ROOT/caddy-data" \
    "$STAGING_TEST_ROOT/caddy-config"

root_password_file="$simulation_root/mysql_root_password"
sed \
    -e 's|CHANGE_ME_GENERATE_WITH_php_artisan_key_generate_show|base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=|' \
    -e "s|https://staging.example.invalid|http://127.0.0.1:$STAGING_TEST_HTTP_PORT|" \
    -e 's|staging.example.invalid|http://localhost|' \
    -e 's|CHANGE_ME_OPERATIONS_EMAIL|ops@example.test|' \
    -e 's|CHANGE_ME_IMMUTABLE_RELEASE|staging-validation|' \
    -e 's|CHANGE_ME_RANDOM_DATABASE_PASSWORD|staging-validation-password|' \
    -e 's|CHANGE_ME_SMTP_HOST|smtp.example.test|' \
    -e 's|CHANGE_ME_SMTP_USERNAME|staging-user|' \
    -e 's|CHANGE_ME_SMTP_PASSWORD|staging-password|' \
    -e 's|CHANGE_ME_VERIFIED_FROM_ADDRESS|no-reply@example.test|' \
    -e "s|MYSQL_ROOT_PASSWORD_FILE=./secrets/mysql_root_password|MYSQL_ROOT_PASSWORD_FILE=$root_password_file|" \
    .env.production.example > "$ENV_FILE"
printf '%s\n' 'staging-validation-root-password' > "$root_password_file"
chmod 600 "$ENV_FILE" "$root_password_file"

compose=(
    docker compose
    --env-file "$ENV_FILE"
    -p "$COMPOSE_PROJECT_NAME"
    -f compose.yaml
    -f compose.production.yaml
    -f compose.staging-test.yaml
)

cleanup() {
    "${compose[@]}" down --timeout 10 --remove-orphans || true
    docker run --rm --entrypoint sh \
        -v "$simulation_root:/simulation" \
        alpine:3.20 \
        -c 'rm -rf /simulation/state' 2>/dev/null || true
    rm -rf "$simulation_root"
}
trap cleanup EXIT

docker run --rm --user root \
    -v "$STAGING_TEST_ROOT/storage:/state/storage" \
    -v "$STAGING_TEST_ROOT/bootstrap-cache:/state/bootstrap-cache" \
    sky-fundi-app:staging-validation \
    sh -c 'chown -R www-data:www-data /state/storage /state/bootstrap-cache'

scripts/deploy.sh

curl -fsS "http://127.0.0.1:$STAGING_TEST_HTTP_PORT/up" >/dev/null
curl -fsS "http://127.0.0.1:$STAGING_TEST_HTTP_PORT/api/v1/ready" >/dev/null
"${compose[@]}" exec -T app php artisan platform:diagnose
"${compose[@]}" exec -T app php artisan schedule:list >/dev/null

BACKUP_DIR="$simulation_root/backups" scripts/backup-database.sh
backup_file="$(find "$simulation_root/backups" -maxdepth 1 -type f -name '*.sql.gz' -print -quit)"
scripts/restore-database-validation.sh "$backup_file" skyfundi_staging_restore_validation

"${compose[@]}" exec -T queue sh -c 'tr "\0" " " < /proc/1/cmdline' | grep -q 'queue:work'
"${compose[@]}" exec -T scheduler sh -c 'tr "\0" " " < /proc/1/cmdline' | grep -q 'schedule:work'

upload_probe="$STAGING_TEST_ROOT/storage/app/private/staging-persistence-probe"
"${compose[@]}" exec -T app sh -c 'printf persistence > storage/app/private/staging-persistence-probe'
"${compose[@]}" up -d --force-recreate app
[[ "$(cat "$upload_probe")" == "persistence" ]]

echo "Isolated staging simulation passed."
