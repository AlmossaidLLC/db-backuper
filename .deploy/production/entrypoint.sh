#!/bin/sh

set -e

# =============================================================================
# Auto-configuration for standalone Docker deployment
# =============================================================================

ENV_FILE="/var/www/html/.env"

# Set default environment variables if not provided
APP_NAME="${APP_NAME:-DB Backuper}"
APP_ENV="${APP_ENV:-production}"
APP_DEBUG="${APP_DEBUG:-false}"
APP_URL="${APP_URL:-http://localhost}"
APP_TIMEZONE="${APP_TIMEZONE:-UTC}"

# Database defaults (SQLite for standalone deployment)
DB_CONNECTION="${DB_CONNECTION:-sqlite}"
DB_DATABASE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"

# Session and cache defaults
SESSION_DRIVER="${SESSION_DRIVER:-database}"
CACHE_STORE="${CACHE_STORE:-database}"
QUEUE_CONNECTION="${QUEUE_CONNECTION:-database}"

# Log configuration
LOG_CHANNEL="${LOG_CHANNEL:-stack}"
LOG_LEVEL="${LOG_LEVEL:-error}"

# =============================================================================
# APP_KEY Generation (auto-generate if not provided)
# =============================================================================
if [ -z "$APP_KEY" ]; then
    # Check if we have a stored key in the persistent database directory
    KEY_FILE="/var/www/html/database/.app_key"

    if [ -f "$KEY_FILE" ]; then
        APP_KEY=$(cat "$KEY_FILE")
        echo "Using existing APP_KEY from storage."
    else
        echo "Generating new APP_KEY..."
        # Generate key without .env file first
        APP_KEY=$(php -r "echo 'base64:' . base64_encode(random_bytes(32));")
        # Store the key for persistence across container restarts
        echo "$APP_KEY" > "$KEY_FILE"
        chmod 600 "$KEY_FILE"
        echo "APP_KEY generated and stored."
    fi
fi

# =============================================================================
# Write .env file (required for PHP-FPM to read environment variables)
# =============================================================================
echo "Writing .env file..."
cat > "$ENV_FILE" << EOF
APP_NAME="${APP_NAME}"
APP_ENV=${APP_ENV}
APP_DEBUG=${APP_DEBUG}
APP_URL=${APP_URL}
APP_TIMEZONE=${APP_TIMEZONE}
APP_KEY=${APP_KEY}

DB_CONNECTION=${DB_CONNECTION}
DB_DATABASE=${DB_DATABASE}
DB_HOST=${DB_HOST:-}
DB_PORT=${DB_PORT:-}
DB_USERNAME=${DB_USERNAME:-}
DB_PASSWORD=${DB_PASSWORD:-}

SESSION_DRIVER=${SESSION_DRIVER}
CACHE_STORE=${CACHE_STORE}
QUEUE_CONNECTION=${QUEUE_CONNECTION}

LOG_CHANNEL=${LOG_CHANNEL}
LOG_LEVEL=${LOG_LEVEL}

MAIL_MAILER=${MAIL_MAILER:-log}
MAIL_HOST=${MAIL_HOST:-}
MAIL_PORT=${MAIL_PORT:-}
MAIL_USERNAME=${MAIL_USERNAME:-}
MAIL_PASSWORD=${MAIL_PASSWORD:-}
MAIL_ENCRYPTION=${MAIL_ENCRYPTION:-}
MAIL_FROM_ADDRESS=${MAIL_FROM_ADDRESS:-}
MAIL_FROM_NAME="${MAIL_FROM_NAME:-\${APP_NAME}}"
EOF

chown www-data:www-data "$ENV_FILE"
chmod 640 "$ENV_FILE"

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
