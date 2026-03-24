#!/bin/sh
set -e

# Clear runtime caches by default to prevent stale route/view hashes after deploys.
if [ "${CLEAR_RUNTIME_CACHE:-true}" = "true" ] || [ "${CLEAR_OPTIMIZE:-false}" = "true" ]; then
    php artisan optimize:clear --quiet 2>/dev/null || true
    php artisan filament:optimize-clear --quiet 2>/dev/null || true
fi

[ -n "${DB_CONNECTION:-}" ] && [ "${SKIP_MIGRATIONS:-false}" != "true" ] && \
    php artisan migrate --seed --force --quiet 2>/dev/null || true

exec "$@"
