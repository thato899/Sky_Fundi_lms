#!/usr/bin/env bash

set -Eeuo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

database="sky_fundi_migrate_check_$$"

cleanup() {
    local command_status=$?

    trap - EXIT

    if ! docker compose exec -T -e MIGRATION_DATABASE="$database" mysql sh -Eeuc '
        MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot \
            -e "DROP DATABASE IF EXISTS \`$MIGRATION_DATABASE\`;"
    ' >/dev/null 2>&1; then
        echo "Failed to remove temporary migration database: $database" >&2
        ((command_status != 0)) && exit "$command_status"
        exit 1
    fi

    exit "$command_status"
}

trap cleanup EXIT

docker compose exec -T -e MIGRATION_DATABASE="$database" mysql sh -Eeuc '
    MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -e "
        CREATE DATABASE \`$MIGRATION_DATABASE\`
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        GRANT ALL PRIVILEGES ON \`$MIGRATION_DATABASE\`.*
            TO '\''$MYSQL_USER'\''@'\''%'\'';
    "
'

docker compose exec -T \
    -e APP_ENV=testing \
    -e DB_DATABASE="$database" \
    app php artisan migrate:fresh --seed --force

docker compose exec -T \
    -e APP_ENV=testing \
    -e DB_DATABASE="$database" \
    app php artisan migrate:rollback --force

docker compose exec -T \
    -e APP_ENV=testing \
    -e DB_DATABASE="$database" \
    app php artisan migrate --force

echo "Migration, seed, rollback, and re-migration checks passed on an isolated MySQL database."
