#!/bin/sh

set -e

# =============================================================================
# Auto-configuration for standalone Docker deployment
# =============================================================================

# Set default environment variables if not provided
export APP_NAME="${APP_NAME:-DB Backuper}"
export APP_ENV="${APP_ENV:-production}"
export APP_DEBUG="${APP_DEBUG:-false}"
export APP_URL="${APP_URL:-http://localhost}"
export APP_TIMEZONE="${APP_TIMEZONE:-UTC}"

# Database defaults (SQLite for standalone deployment)
export DB_CONNECTION="${DB_CONNECTION:-sqlite}"
export DB_DATABASE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"

# Session and cache defaults
export SESSION_DRIVER="${SESSION_DRIVER:-database}"
export CACHE_STORE="${CACHE_STORE:-database}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-database}"

# Log configuration
export LOG_CHANNEL="${LOG_CHANNEL:-stack}"
export LOG_LEVEL="${LOG_LEVEL:-error}"

# =============================================================================
# APP_KEY Generation (auto-generate if not provided)
# =============================================================================
if [ -z "$APP_KEY" ]; then
    # Check if we have a stored key in the persistent database directory
    KEY_FILE="/var/www/html/database/.app_key"

    if [ -f "$KEY_FILE" ]; then
        export APP_KEY=$(cat "$KEY_FILE")
        echo "Using existing APP_KEY from storage."
    else
        echo "Generating new APP_KEY..."
        export APP_KEY=$(php artisan key:generate --show)
        # Store the key for persistence across container restarts
        echo "$APP_KEY" > "$KEY_FILE"
        chmod 600 "$KEY_FILE"
        echo "APP_KEY generated and stored."
    fi
fi

# =============================================================================
# Database Setup
# =============================================================================

# Create SQLite database if using SQLite and database doesn't exist
if [ "$DB_CONNECTION" = "sqlite" ]; then
    if [ ! -f "$DB_DATABASE" ]; then
        echo "Creating SQLite database..."
        touch "$DB_DATABASE"
        chown www-data:www-data "$DB_DATABASE"
        chmod 664 "$DB_DATABASE"
    fi
fi

# =============================================================================
# Laravel Initialization
# =============================================================================

echo "Putting application in maintenance mode..."
php artisan down --quiet 2>/dev/null || true

echo "Running database migrations..."
php artisan migrate --seed --force --quiet

echo "Optimizing application for production..."
php artisan config:cache --quiet
php artisan route:cache --quiet
php artisan view:cache --quiet

# Set proper permissions
echo "Setting permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
if [ "$DB_CONNECTION" = "sqlite" ] && [ -f "$DB_DATABASE" ]; then
    chown www-data:www-data "$DB_DATABASE"
    chown www-data:www-data "$(dirname "$DB_DATABASE")"
fi

echo "Bringing application online..."
php artisan up --quiet

echo "============================================"
echo "DB Backuper is ready!"
echo "============================================"

# Execute the main command (supervisord)
exec "$@"
