# Cómo probar Rodante

Todo lo que corre el CI (`.github/workflows/tests.yml`), y cómo correrlo a mano.

## 1. Suite PHP (rápida, SQLite en memoria)

```bash
php artisan test
```

`phpunit.xml` fuerza SQLite `:memory:`: no toca ninguna base real. 228 tests.

## 2. Suite PHP contra MySQL 8 (como producción)

```bash
php artisan test --configuration=phpunit.mysql.xml
```

`phpunit.mysql.xml` espera MySQL en `127.0.0.1:3306`, base `testing`, usuario `root/root`
(lo levanta el CI). Para otra base, copiá el archivo y cambiá las variables `DB_*`;
**nunca** la apuntes a la base de desarrollo: la suite la borra.

Diferencias esperables entre motores: en MySQL las restricciones `CHECK` y los triggers
frenan estados corruptos antes que el chequeo de integridad (los tests lo contemplan).

## 3. Estilo y dependencias

```bash
vendor/bin/pint --test     # formato (el CI falla si no pasa)
composer audit             # vulnerabilidades conocidas
```

## 4. E2E (Playwright)

```bash
npm ci && npm run build
php artisan migrate --force && php artisan db:seed --force    # base de demo
php artisan serve --port=8093
npx playwright test e2e/a11y-login.spec.ts e2e/critical-flow.spec.ts
```

- `a11y-login`: axe sin violaciones AA críticas en login y recupero de contraseña.
- `critical-flow`: login `admin/password` y navegación por compras, unidades y campo.

Otra URL: `PLAYWRIGHT_BASE_URL=http://…`.

## 5. App móvil (Expo)

```bash
cd mobile && npm ci
npm run typecheck
npm test
```

## 6. QA por rol

```bash
php artisan qa:roles      # recorre cada rol; logs en storage/logs/qa/ y docs/qa/
```

## Problemas comunes

- **`storage/` sin permisos** (archivos creados por el contenedor como `www-data`):
  corré los tests dentro de Docker (`make test`) o apuntá `storage` a otra carpeta:
  `LARAVEL_STORAGE_PATH=/tmp/rodante-storage php artisan test`
  (con `logs/`, `framework/{cache,sessions,views,testing}` y `app/public` creados).
- **`artisan serve` ignora variables de entorno**: pasa solo una lista corta al proceso
  hijo. Para probar con otra base, ponelas en `.env` o usá Docker.
