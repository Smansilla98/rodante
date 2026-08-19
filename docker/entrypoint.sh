#!/bin/sh
set -e
cd /var/www/html

mkdir -p storage/logs \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
fi

if [ -f .env ] && ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  php artisan key:generate --force
fi

php artisan migrate --force

if [ "${SEED_DEMO:-0}" = "1" ] || [ "${SEED_DEMO:-0}" = "true" ]; then
  php artisan db:seed --force
fi

PORT="${PORT:-8080}"
if [ -f /etc/nginx/templates/rodante.conf.template ]; then
  sed "s/LISTEN_PORT/${PORT}/g" /etc/nginx/templates/rodante.conf.template > /etc/nginx/conf.d/rodante.conf
fi

if [ "$#" -eq 0 ]; then
  set -- php-fpm
fi

exec "$@"
