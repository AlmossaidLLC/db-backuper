# DB Backuper

A Laravel application with Filament admin panel for managing database backups using mysqldump.

## Features

- Laravel 12 framework
- Filament v4 admin panel
- Database backups
- Queue processing for background tasks
- Cron jobs for scheduled backups
- Optimized for production deployment

## Requirements

- PHP 8.2+
- SQLite database
- Composer

## Local Development

1. Clone the repository
2. Install dependencies:
   ```bash
   composer install
   ```
3. Copy environment file:
   ```bash
   cp .env.example .env
   ```
4. Generate app key:
   ```bash
   php artisan key:generate
   ```
5. Configure your database in `.env`
6. Run migrations:
   ```bash
   php artisan migrate
   ```
7. Start the development server:
   ```bash
   php artisan serve
   ```

## Docker Build

To build the Docker image for production:

```bash
docker build -f .deploy/Dockerfile -t db-backuper:latest .
```

## Docker Run

To run the container with live syncing (recommended for development):

```bash
docker run -d \
  --name db-backuper \
  -p 9033:80 \
  -v $(pwd):/var/www/html \
  -v $(pwd)/database:/var/www/html/database \
  -e APP_KEY=your_generated_key_here \
  -e DB_CONNECTION=sqlite \
  db-backuper:latest
```

**Note**: Replace `your_generated_key_here` with the output of `php artisan key:generate --show`.

## Caprover Deployment

This project is configured for easy deployment on Caprover.

1. Push your code to a Git repository
2. In Caprover dashboard, create a new app
3. Connect to your Git repo
4. The `captain-definition` file will automatically configure:
   - Resource limits: 2 CPU cores, 4GB RAM
   - Environment variables for database connection
   - Automatic deployment with Docker

### Environment Variables

Set these in your Caprover app configuration:

- `APP_NAME`: Your app name
- `APP_ENV`: production
- `APP_KEY`: Generate with `php artisan key:generate`
- `DB_CONNECTION`: sqlite
- `DB_DATABASE`: /var/www/html/database/database.sqlite

## Background Processes

The Docker container runs:
- PHP-FPM for web requests
- Queue worker for background jobs
- Cron daemon for scheduled tasks (Laravel scheduler)

## Admin Panel

Access the Filament admin panel at `/admin` after deployment.

Create your first admin user by visiting `/admin` and following the setup instructions.

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
