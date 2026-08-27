# Etapa 1: Vite → public/build (requerido por @vite en Blade en producción).
FROM node:22-alpine AS vite
WORKDIR /app
COPY package.json package-lock.json ./
# Playwright no hace falta para Vite; omitir optional rompería @rolldown/binding-*-musl.
ENV PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# Laravel en Railway: nginx escucha $PORT y hace proxy a PHP-FPM (un solo contenedor).
FROM php:8.3-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip nginx \
    libzip-dev libonig-dev libpq-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql pdo_pgsql zip opcache mbstring bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
COPY --from=vite /app/public/build ./public/build

RUN rm -f /etc/nginx/sites-enabled/default \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts \
    && chown -R www-data:www-data storage bootstrap/cache public/build \
    && chmod -R ug+rwx storage bootstrap/cache public/build

COPY docker/nginx/railway.default.conf /opt/railway-nginx.conf
COPY docker/entrypoint-railway.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
