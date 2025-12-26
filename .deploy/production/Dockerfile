# Build stage for Composer dependencies
FROM php:8.3-cli-alpine AS build

# Install extensions required for Composer dependencies
RUN apk add --no-cache libzip-dev icu-dev \
    && docker-php-ext-install zip intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy only composer files for better layer caching
COPY composer.json composer.lock ./

# Install production dependencies only (skip scripts since artisan doesn't exist yet)
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist \
    --no-scripts \
    && composer clear-cache

# Runtime stage
FROM php:8.3-fpm-alpine

# Install runtime dependencies only
RUN apk add --no-cache \
    libpng \
    libjpeg-turbo \
    freetype \
    libzip \
    oniguruma \
    mysql-client \
    supervisor \
    caddy \
    icu \
    && rm -rf /var/cache/apk/*

# Install PHP extensions with build dependencies cleaned in same layer
RUN apk add --no-cache --virtual .build-deps \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

# Set working directory
WORKDIR /var/www/html

# Copy vendor from build stage
COPY --from=build /app/vendor ./vendor

# Copy deployment configuration files (change less frequently, better layer caching)
COPY .deploy/production/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY .deploy/production/supervisord.conf /etc/supervisord.conf
COPY .deploy/production/Caddyfile /etc/caddy/Caddyfile
COPY .deploy/production/php.ini /usr/local/etc/php/conf.d/custom.ini
RUN chmod +x /usr/local/bin/entrypoint.sh

# Copy application code
COPY . .

# Run Laravel post-install scripts (package discovery)
RUN php artisan package:discover --ansi || true

# Production optimizations: clean up unnecessary files and set permissions
RUN rm -rf \
    tests \
    .git \
    .github \
    node_modules \
    storage/logs/* \
    storage/framework/cache/* \
    storage/framework/sessions/* \
    storage/framework/views/* \
    bootstrap/cache/* \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache \
    && mkdir -p /var/log/supervisor /var/log/php8.3-fpm \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/log/php8.3-fpm \
    && chmod 755 /var/log/supervisor

# Optimize PHP-FPM pool configuration for production (ondemand mode saves resources)
RUN sed -i \
    -e 's/^pm = dynamic/pm = ondemand/' \
    -e 's/^pm.max_children = 5/pm.max_children = 50/' \
    -e 's/^pm.process_idle_timeout = 10s/pm.process_idle_timeout = 10s/' \
    -e 's/^;pm.max_requests = 500/pm.max_requests = 500/' \
    -e 's/^;clear_env = no/clear_env = no/' \
    /usr/local/etc/php-fpm.d/www.conf

# Health check (check if PHP-FPM process is running)
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD ps aux | grep -q '[p]hp-fpm' || exit 1

# Expose port 80 for Caddy
EXPOSE 80

# Set entrypoint and default command
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

