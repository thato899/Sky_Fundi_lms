#!/usr/bin/env bash

set -Eeuo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

phpstan=(docker compose exec -T app ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M)

if [[ "${ANALYSE_ALL:-0}" == 1 ]]; then
    "${phpstan[@]}"
    exit
fi

mapfile -t paths < <(
    git ls-files --modified --others --exclude-standard -- '*.php' \
        | grep -Ev '(^|/)(tests|database/migrations|routes)/' \
        | while IFS= read -r path; do
            [[ -f "$path" ]] && printf '%s\n' "$path"
        done
)

if ((${#paths[@]} == 0)); then
    echo "No changed production PHP files require static analysis."
    exit
fi

printf 'Analysing %d changed production PHP file(s).\n' "${#paths[@]}"
"${phpstan[@]}" "${paths[@]}"
