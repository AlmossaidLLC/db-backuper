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

echo "Running migrations and seeders..."
php artisan migrate --seed --force

echo "Setting database permissions..."
chown www-data:www-data /var/www/html/database/database.sqlite

echo "Migrations and seeders completed."

echo "Running the application..."
php artisan up

# Execute the main command (supervisord)
exec "$@"
