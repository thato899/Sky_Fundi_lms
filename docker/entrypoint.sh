#!/bin/sh
set -eu

# Runtime startup is deliberately side-effect free. Migrations, cache
# generation, and the storage link are explicit deployment actions.
umask 0002

exec "$@"
