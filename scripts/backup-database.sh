#!/usr/bin/env bash
set -Eeuo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

env_file="${ENV_FILE:-.env.production}"
export PRODUCTION_ENV_FILE="$(realpath "$env_file")"
backup_dir="${BACKUP_DIR:-./backups/database}"
compose=(
    docker compose
    --env-file "$env_file"
    -f compose.yaml
    -f compose.production.yaml
)
if [[ -n "${COMPOSE_EXTRA_FILE:-}" ]]; then
    compose+=(-f "$COMPOSE_EXTRA_FILE")
fi

scripts/validate-production-env.sh "$env_file"
command -v gzip >/dev/null || {
    echo "gzip is required." >&2
    exit 1
}

mkdir -p "$backup_dir"
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
database="$(awk -F= '$1 == "DB_DATABASE" {sub(/^[^=]*=/, ""); print; exit}' "$env_file")"
output="$backup_dir/${database}_${timestamp}.sql.gz"
temporary="${output}.partial"
trap 'rm -f "$temporary"' EXIT

"${compose[@]}" exec -T mysql sh -eu -c \
    'MYSQL_PWD="$MYSQL_PASSWORD" exec mysqldump --single-transaction --quick --routines --triggers --no-tablespaces -u"$MYSQL_USER" "$MYSQL_DATABASE"' \
    | gzip -9 > "$temporary"

gzip -t "$temporary"
[[ -s "$temporary" ]] || {
    echo "Backup output is empty." >&2
    exit 1
}
mv "$temporary" "$output"
trap - EXIT

echo "Verified database backup: $output"
