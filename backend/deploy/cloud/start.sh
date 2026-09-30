#!/bin/sh
set -e
cd /var/www/html

# Railway injects PORT; default to 8080 elsewhere.
export PORT="${PORT:-8080}"
envsubst '${PORT}' < /etc/nginx/templates/app.conf.template > /etc/nginx/conf.d/app.conf

# A mounted volume replaces the storage folder, so recreate its structure.
mkdir -p storage/app/private storage/app/public/attachments \
         storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ -z "$APP_KEY" ]; then
  echo "APP_KEY is not set. Generate one locally with: php artisan key:generate --show" >&2
  exit 1
fi

php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Artisan ran as root above; hand any files it created back to PHP-FPM.
chown -R www-data:www-data storage bootstrap/cache

exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
