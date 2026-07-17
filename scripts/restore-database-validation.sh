#!/usr/bin/env bash
set -Eeuo pipefail

if [[ "$#" -ne 2 ]]; then
    echo "Usage: $0 BACKUP.sql.gz VALIDATION_DATABASE" >&2
    exit 2
fi

backup="$1"
validation_database="$2"
env_file="${ENV_FILE:-.env.production}"
export PRODUCTION_ENV_FILE="$(realpath "$env_file")"

[[ -r "$backup" ]] || {
    echo "Backup is not readable: $backup" >&2
    exit 1
}
gzip -t "$backup"

production_database="$(awk -F= '$1 == "DB_DATABASE" {sub(/^[^=]*=/, ""); print; exit}' "$env_file")"
if [[ "$validation_database" == "$production_database" ]]; then
    echo "Refusing to restore into the configured production database." >&2
    exit 1
fi
if [[ ! "$validation_database" =~ ^[A-Za-z0-9_]+_restore_validation$ ]]; then
    echo "Validation database must end in _restore_validation." >&2
    exit 1
fi

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"
compose=(
    docker compose
    --env-file "$env_file"
    -f compose.yaml
    -f compose.production.yaml
)
if [[ -n "${COMPOSE_EXTRA_FILE:-}" ]]; then
    compose+=(-f "$COMPOSE_EXTRA_FILE")
fi

"${compose[@]}" exec -T mysql sh -eu -c \
    'MYSQL_PWD="$(cat /run/secrets/mysql_root_password)" mysql -uroot -e "CREATE DATABASE IF NOT EXISTS \`'"$validation_database"'\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"'

gzip -dc "$backup" | "${compose[@]}" exec -T mysql sh -eu -c \
    'MYSQL_PWD="$(cat /run/secrets/mysql_root_password)" mysql -uroot '"$validation_database"

"${compose[@]}" exec -T mysql sh -eu -c \
    'MYSQL_PWD="$(cat /run/secrets/mysql_root_password)" mysql -uroot -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '"'"''"$validation_database"''"'"'"'

echo "Restore validation completed in non-production database: $validation_database"
echo "Drop it only after review; this script never deletes databases or volumes."
