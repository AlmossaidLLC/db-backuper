#!/bin/sh

# Wait for the database to be ready (optional, adjust as needed)
# You can use a tool like wait-for-it or a simple loop
# For example, if using MySQL:
# while ! mysqladmin ping -h db -u root -p$DB_PASSWORD --silent; do
#   echo "Waiting for database..."
#   sleep 1
# done

php artisan down
echo "Application is in maintenance mode."

echo "Installing/updating dependencies..."
composer install --no-interaction --optimize-autoloader || echo "Composer install failed or already up to date"

echo "Regenerating autoloader..."
composer dump-autoload --no-interaction --optimize || echo "Autoloader regeneration failed"

echo "Running migrations and seeders..."
php artisan migrate --seed --force

echo "Setting database permissions..."
chown www-data:www-data /var/www/html/database/database.sqlite

echo "Migrations and seeders completed."

echo "Running the application..."
php artisan up

# Execute the main command (supervisord)
exec "$@"

