#!/usr/bin/env bash
set -Eeuo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

for script in \
    scripts/deploy.sh \
    scripts/backup-database.sh \
    scripts/restore-database-validation.sh \
    scripts/simulate-staging.sh \
    scripts/validate-production-env.sh \
    scripts/validate-deployment.sh; do
    bash -n "$script"
done

temporary="$(mktemp -d)"
trap 'rm -rf "$temporary"' EXIT
env_file="$temporary/production.env"
root_password_file="$temporary/mysql_root_password"

sed \
    -e 's|CHANGE_ME_GENERATE_WITH_php_artisan_key_generate_show|base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=|' \
    -e 's|https://staging.example.invalid|http://localhost|' \
    -e 's|staging.example.invalid|http://localhost|' \
    -e 's|CHANGE_ME_OPERATIONS_EMAIL|ops@example.test|' \
    -e 's|CHANGE_ME_IMMUTABLE_RELEASE|validation|' \
    -e 's|CHANGE_ME_RANDOM_DATABASE_PASSWORD|validation-database-password|' \
    -e 's|CHANGE_ME_SMTP_HOST|smtp.example.test|' \
    -e 's|CHANGE_ME_SMTP_USERNAME|validation-user|' \
    -e 's|CHANGE_ME_SMTP_PASSWORD|validation-password|' \
    -e 's|CHANGE_ME_VERIFIED_FROM_ADDRESS|no-reply@example.test|' \
    -e "s|MYSQL_ROOT_PASSWORD_FILE=./secrets/mysql_root_password|MYSQL_ROOT_PASSWORD_FILE=$root_password_file|" \
    -e 's|HTTP_BIND_ADDRESS=0.0.0.0|HTTP_BIND_ADDRESS=127.0.0.1|' \
    -e 's|HTTP_PORT=80|HTTP_PORT=18080|' \
    -e 's|HTTPS_BIND_ADDRESS=0.0.0.0|HTTPS_BIND_ADDRESS=127.0.0.1|' \
    -e 's|HTTPS_PORT=443|HTTPS_PORT=18443|' \
    .env.production.example > "$env_file"
printf '%s\n' 'validation-root-password' > "$root_password_file"
chmod 600 "$root_password_file"

scripts/validate-production-env.sh "$env_file"
export PRODUCTION_ENV_FILE="$env_file"

compose=(
    docker compose
    --env-file "$env_file"
    -p sky-fundi-deployment-validation
    -f compose.yaml
    -f compose.production.yaml
)
"${compose[@]}" config --quiet
rendered="$("${compose[@]}" config)"
services="$("${compose[@]}" config --services)"

if grep -qx 'mailpit' <<< "$services"; then
    echo "Mailpit must be absent from the production service set." >&2
    exit 1
fi
if grep -qx 'redis' <<< "$services"; then
    echo "Optional development Redis must be absent from production." >&2
    exit 1
fi
if "${compose[@]}" config mysql | grep -q 'published:'; then
    echo "MySQL must not publish a host port." >&2
    exit 1
fi
for volume in mysql storage bootstrap_cache caddy_data caddy_config; do
    grep -q "sky-fundi-deployment-validation_${volume}" <<< "$rendered" || {
        echo "Missing persistent production volume: $volume" >&2
        exit 1
    }
done
for service in app queue scheduler web; do
    "${compose[@]}" config "$service" | grep -q 'restart: unless-stopped' || {
        echo "Missing restart policy for service: $service" >&2
        exit 1
    }
done

invalid_env="$temporary/invalid.env"
sed 's/^APP_DEBUG=false$/APP_DEBUG=true/' "$env_file" > "$invalid_env"
if scripts/validate-production-env.sh "$invalid_env" >/dev/null 2>&1; then
    echo "Unsafe production APP_DEBUG unexpectedly passed validation." >&2
    exit 1
fi

grep -q '^APP_ENV=production$' .env.production.example
grep -q '^APP_DEBUG=false$' .env.production.example
if rg -n '(BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY|AKIA[0-9A-Z]{16})' \
    compose.production.yaml Dockerfile docker/caddy scripts .env.production.example docs/operations/staging-deployment.md 2>/dev/null; then
    echo "A credential-like value appears in deployment files." >&2
    exit 1
fi

echo "Deployment configuration validation passed."
