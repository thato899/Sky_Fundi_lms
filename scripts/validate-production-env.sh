#!/usr/bin/env bash
set -Eeuo pipefail

env_file="${1:-.env.production}"

if [[ ! -r "$env_file" ]]; then
    echo "Production environment file is not readable: $env_file" >&2
    exit 1
fi

declare -A values=()
while IFS='=' read -r key value; do
    [[ "$key" =~ ^[A-Z][A-Z0-9_]*$ ]] || continue
    value="${value%$'\r'}"
    value="${value%\"}"
    value="${value#\"}"
    values["$key"]="$value"
done < "$env_file"

required=(
    APP_NAME APP_ENV APP_KEY APP_URL APP_DOMAIN ACME_EMAIL APP_IMAGE_TAG
    DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
    CACHE_STORE SESSION_DRIVER QUEUE_CONNECTION MAIL_MAILER MAIL_HOST
    MAIL_FROM_ADDRESS FILESYSTEM_DISK LOG_CHANNEL
    OBSERVABILITY_REQUEST_ID_HEADER OBSERVABILITY_SLOW_REQUEST_MS
    OBSERVABILITY_SLOW_QUERY_MS
)

failed=0
for key in "${required[@]}"; do
    value="${values[$key]:-}"
    if [[ -z "$value" || "$value" == CHANGE_ME* || "$value" == *example.invalid* ]]; then
        echo "Missing or placeholder production value: $key" >&2
        failed=1
    fi
done

if [[ "${values[APP_ENV]:-}" != "production" ]]; then
    echo "APP_ENV must be production." >&2
    failed=1
fi

if [[ "${values[APP_DEBUG]:-}" != "false" ]]; then
    echo "APP_DEBUG must be false." >&2
    failed=1
fi

if [[ ! "${values[APP_KEY]:-}" =~ ^base64:.+ ]]; then
    echo "APP_KEY must be a securely generated base64 Laravel key." >&2
    failed=1
fi

if [[ "${values[SESSION_SECURE_COOKIE]:-}" != "true" ]]; then
    echo "SESSION_SECURE_COOKIE must be true." >&2
    failed=1
fi

if [[ "${values[MAIL_HOST]:-}" == "mailpit" ]]; then
    echo "MAIL_HOST must not use Mailpit in production." >&2
    failed=1
fi

if [[ "${values[DB_HOST]:-}" != "mysql" ]]; then
    echo "DB_HOST must be mysql for the bundled single-server stack." >&2
    failed=1
fi

exit "$failed"
