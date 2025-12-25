# Development Dockerfile for local development
FROM php:8.3-fpm-alpine

# Install runtime dependencies
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
    git \
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

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy deployment configuration files
COPY .deploy/development/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY .deploy/development/supervisord.conf /etc/supervisord.conf
COPY .deploy/development/Caddyfile /etc/caddy/Caddyfile
COPY .deploy/development/php.ini /usr/local/etc/php/conf.d/custom.ini
RUN chmod +x /usr/local/bin/entrypoint.sh

# Create necessary directories and set permissions
# Note: For development, directories will be created if they don't exist
# The volume mount will sync the actual code, but permissions need to be set
RUN mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache \
    /var/log/supervisor /var/log/php8.3-fpm \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/log/php8.3-fpm \
    && chmod 755 /var/log/supervisor

# Configure PHP-FPM for development (dynamic mode for better responsiveness)
RUN sed -i \
    -e 's/^pm = dynamic/pm = dynamic/' \
    -e 's/^pm.max_children = 5/pm.max_children = 20/' \
    -e 's/^pm.start_servers = 2/pm.start_servers = 3/' \
    -e 's/^pm.min_spare_servers = 1/pm.min_spare_servers = 2/' \
    -e 's/^pm.max_spare_servers = 3/pm.max_spare_servers = 5/' \
    -e 's/^;pm.max_requests = 500/pm.max_requests = 0/' \
    -e 's/^;clear_env = no/clear_env = no/' \
    /usr/local/etc/php-fpm.d/www.conf

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD ps aux | grep -q '[p]hp-fpm' || exit 1

# Expose port 80 for Caddy
EXPOSE 80

# Set entrypoint and default command
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

