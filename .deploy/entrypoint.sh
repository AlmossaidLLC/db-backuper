#!/bin/sh
set -e

[ "${CLEAR_OPTIMIZE:-false}" = "true" ] && php artisan optimize:clear --quiet 2>/dev/null || true

[ -n "${DB_CONNECTION:-}" ] && [ "${SKIP_MIGRATIONS:-false}" != "true" ] && \
    php artisan migrate --seed --force --quiet 2>/dev/null || true

exec "$@"
