#!/bin/sh
set -eu

# Runtime services deliberately do not initialise the shared source tree.
# The Compose init service owns that work and records completion before app,
# queue, and scheduler are started.
exec "$@"
