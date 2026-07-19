#!/bin/sh
set -eu

# Runtime startup is deliberately side-effect free. Migrations, cache
# generation, and the storage link are explicit deployment actions.
umask 0002

if [ "${SKIP_STARTUP_VALIDATION:-false}" != "true" ]; then
    php artisan platform:validate-environment
fi

exec "$@"
