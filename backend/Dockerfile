# Inquiry Portal API — single container for a small demo deployment:
# Nginx + PHP-FPM + queue worker + scheduler, managed by Supervisor.
FROM php:8.3-fpm-bookworm

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
 && apt-get install -y --no-install-recommends nginx supervisor gettext-base unzip libzip-dev \
 && docker-php-ext-install pdo_mysql opcache pcntl zip \
 && rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled/default

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install dependencies first for better layer caching.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist \
 && composer audit --locked --no-dev

COPY . .

# composer.json's pre-autoload-dump script uses Windows-style quoting that
# breaks under Linux sh, so skip scripts here and do the same work directly.
RUN mkdir -p bootstrap/cache storage/app/private storage/app/public/attachments \
             storage/framework/cache/data storage/framework/sessions \
             storage/framework/views storage/logs \
 && composer dump-autoload --optimize --no-dev --no-scripts \
 && php artisan package:discover --ansi \
 && chown -R www-data:www-data storage bootstrap/cache

COPY deploy/cloud/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY deploy/cloud/nginx.conf.template /etc/nginx/templates/app.conf.template
COPY deploy/cloud/supervisord.conf /etc/supervisor/conf.d/app.conf
COPY deploy/cloud/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

EXPOSE 8080
CMD ["/usr/local/bin/start.sh"]