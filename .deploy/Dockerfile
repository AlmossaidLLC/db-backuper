# Base already has: PHP 8.3, Composer, mysql-client, postgresql-client, supervisor, node, npm
FROM almossaidllc/php-laravel-base:8.3

WORKDIR /var/www/html

RUN apk add --no-cache caddy

COPY .deploy/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY .deploy/supervisord.conf /etc/supervisord.conf
COPY .deploy/Caddyfile /etc/caddy/Caddyfile
COPY .deploy/php.ini /usr/local/etc/php/conf.d/custom.ini
RUN chmod +x /usr/local/bin/entrypoint.sh

# Install PHP dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts

# Copy application source
COPY . .

# Build frontend assets (Filament theme CSS)
RUN npm ci --no-audit --no-fund \
    && npm run build \
    && rm -rf node_modules

RUN php artisan package:discover --ansi \
    && php artisan route:cache --quiet \
    && php artisan view:cache --quiet \
    && rm -rf storage/logs/* storage/framework/cache/data/* storage/framework/sessions/* storage/framework/views/* \
    && mkdir -p /var/log/supervisor \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database /var/log/supervisor \
    && chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache /var/log/supervisor \
    && sed -i -e 's/^pm = dynamic/pm = ondemand/' \
    -e 's/^pm.max_children = 5/pm.max_children = 50/' \
    -e 's/^;pm.max_requests = 500/pm.max_requests = 500/' \
    -e 's/^;clear_env = no/clear_env = no/' \
    /usr/local/etc/php-fpm.d/www.conf

HEALTHCHECK --interval=5s --timeout=2s --start-period=20s --retries=2 \
    CMD curl -f http://localhost/up || exit 1

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

