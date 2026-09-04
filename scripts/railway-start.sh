#!/usr/bin/env bash
# Arranque en Railway: genera nginx en $PORT y levanta supervisord.
# El startCommand de Railway reemplaza el CMD del Dockerfile y NO corre el
# ENTRYPOINT, así que acá hay que dejar nginx escuchando el puerto inyectado.
set -euo pipefail
cd /var/www/html

PORT="${PORT:-8080}"

mkdir -p storage/logs \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  bootstrap/cache \
  /var/log/nginx \
  /var/log/supervisor \
  /run/nginx

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwx storage bootstrap/cache

rm -f /etc/nginx/sites-enabled/default /etc/nginx/conf.d/default.conf

TEMPLATE="${NGINX_TEMPLATE:-/etc/nginx/templates/rodante.conf.template}"
if [[ ! -f "$TEMPLATE" ]]; then
  TEMPLATE="/var/www/html/docker/nginx/railway.conf.template"
fi
if [[ ! -f "$TEMPLATE" ]]; then
  echo "[railway-start] ERROR: no está el template de nginx" >&2
  exit 1
fi

sed "s/LISTEN_PORT/${PORT}/g" "$TEMPLATE" > /etc/nginx/conf.d/rodante.conf
echo "[railway-start] nginx listen 0.0.0.0:${PORT}"
nginx -t

if [[ -f artisan ]]; then
  echo "[railway-start] migraciones"
  php artisan migrate --force --no-interaction || true
  if [[ "${SEED_DEMO:-1}" == "1" || "${SEED_DEMO:-1}" == "true" ]]; then
    echo "[railway-start] seeders (usuarios de prueba)"
    php artisan db:seed --force --no-interaction || true
    echo "[railway-start] mapeo completo por patente"
    php artisan db:seed --class=Database\\Seeders\\CompletePlateMapSeeder --force --no-interaction || true
  fi
fi

exec supervisord -n -c /etc/supervisor/conf.d/rodante.conf
