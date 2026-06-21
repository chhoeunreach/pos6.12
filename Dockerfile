FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libonig-dev libxml2-dev libzip-dev nginx \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

COPY . /var/www
WORKDIR /var/www

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    composer install --no-dev --no-interaction --optimize-autoloader 2>&1 | tail -5

RUN chmod -R 775 storage bootstrap/cache && \
    chown -R www-data:www-data storage bootstrap/cache && \
    rm -f /etc/nginx/sites-enabled/*; \
    rm -f /var/www/.env; \
    php artisan config:clear 2>/dev/null; \
    php artisan route:clear 2>/dev/null; \
    rm -rf /var/www/storage/framework/cache/data 2>/dev/null; \
    true

COPY nginx/default.conf /etc/nginx/sites-enabled/default

RUN sed -i 's/^pm.max_children = 5/pm.max_children = 25/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/^pm.start_servers = 2/pm.start_servers = 10/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/^pm.min_spare_servers = 1/pm.min_spare_servers = 5/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/^pm.max_spare_servers = 3/pm.max_spare_servers = 15/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/^;pm.max_requests = 500/pm.max_requests = 500/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/^;request_terminate_timeout = 0/request_terminate_timeout = 30s/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/^[[:space:]]*worker_connections 768/worker_connections 1024/' /etc/nginx/nginx.conf

EXPOSE 80
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
