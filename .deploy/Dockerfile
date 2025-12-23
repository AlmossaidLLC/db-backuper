# Use the official PHP 8.3 FPM Alpine image for a lightweight base
FROM php:8.3-fpm-alpine

# Install system dependencies, MySQL client for mysqldump, and PHP extensions required for Laravel
RUN apk add --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    mysql-client \
    supervisor \
    dcron \
    caddy \
    icu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install PHP dependencies (production only)
    RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
# Copy the rest of the application
COPY . .

# Copy and set entrypoint
COPY .deploy/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && mkdir -p /var/log/supervisor /var/log/php8.3-fpm

# Copy supervisor configuration
COPY .deploy/supervisord.conf /etc/supervisord.conf

# Copy Caddy configuration
COPY .deploy/Caddyfile /etc/caddy/Caddyfile

# Copy cron job for Laravel scheduler
RUN echo "* * * * * /usr/local/bin/php /var/www/html/artisan schedule:run >> /dev/null 2>&1" > /etc/crontabs/www-data

# Expose port 80 for Caddy (will be handled by Caprover)
EXPOSE 80

# Set entrypoint and default command
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
