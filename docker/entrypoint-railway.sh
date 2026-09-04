#!/bin/sh
set -e
cd /var/www/html

PORT="${PORT:-8080}"
sed "s/__PORT__/${PORT}/" /opt/railway-nginx.conf > /etc/nginx/conf.d/default.conf

mkdir -p storage/logs \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwx storage bootstrap/cache

if [ -f artisan ]; then
    echo "=== Esperando base de datos ==="
    for i in $(seq 1 30); do
        if php -r "
            try {
                new PDO(
                    'mysql:host='.(getenv('DB_HOST') ?: '127.0.0.1').
                    ';port='.(getenv('DB_PORT') ?: '3306').
                    ';dbname='.(getenv('DB_DATABASE') ?: ''),
                    getenv('DB_USERNAME') ?: 'root',
                    getenv('DB_PASSWORD') ?: '',
                    [PDO::ATTR_TIMEOUT => 2]
                );
                exit(0);
            } catch (Exception \$e) {
                exit(1);
            }
        " 2>/dev/null; then
            echo "Base de datos disponible"
            break
        fi
        echo "Intento $i/30..."
        sleep 2
    done

    echo "=== Ejecutando migraciones ==="
    php artisan migrate --force --no-interaction || {
        echo "ADVERTENCIA: las migraciones fallaron. Verificá los logs."
    }

    if [ "${SEED_DEMO:-1}" = "1" ] || [ "${SEED_DEMO:-1}" = "true" ]; then
        echo "=== Cargando seeders (usuarios de prueba) ==="
        php artisan db:seed --force --no-interaction || true
        echo "=== Mapeo completo por patente ==="
        php artisan db:seed --class=Database\\Seeders\\CompletePlateMapSeeder --force --no-interaction || true
    fi

    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true

    php artisan schedule:work --verbose --no-interaction &
fi

php-fpm -D
exec nginx -g "daemon off;"
