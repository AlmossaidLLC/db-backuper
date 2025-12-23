# Build stage for Composer dependencies
FROM php:8.3-cli-alpine AS build

# Install extensions required for Composer dependencies
RUN apk add --no-cache libzip-dev icu-dev \
    && docker-php-ext-install zip intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Runtime stage
FROM php:8.3-fpm-alpine

# Install runtime dependencies and build tools (remove build tools after)
RUN apk add --no-cache \
    libpng \
    libjpeg-turbo \
    freetype \
    libzip \
    oniguruma \
    mysql-client \
    supervisor \
    dcron \
    caddy \
    icu \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl \
    && apk del libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev oniguruma-dev icu-dev

# Set working directory
WORKDIR /var/www/html

# Copy vendor from build stage
COPY --from=build /app/vendor ./vendor

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
