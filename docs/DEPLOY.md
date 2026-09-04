# Deploy — Rodante

## Local (Docker Compose)

HTTP lo sirve **nginx** (puerto `8093`) y habla con **PHP-FPM** del servicio `app`.

```bash
SEED_DEMO=1 docker compose up --build
```

Demo: http://localhost:8093 — usuarios `admin` / `jefe` / `logistica` / `operario` / `consulta`, contraseña `password`.

`SEED_DEMO=1` corre `php artisan db:seed` en el entrypoint del contenedor `app` (después de migrar). El seeder es idempotente: si ya existe la unidad `HKH 448` no duplica el lote, pero sí asegura usuarios, recapadora demo y **mapas completos por patente** (`CompletePlateMapSeeder`). Para sólo completar mapas en una base ya seedada: `php artisan db:seed --class=CompletePlateMapSeeder`.

## Railway (producción)

Un solo contenedor (`Dockerfile`, igual que el resto de las apps Laravel del equipo):

1. **Build:** Vite (`public/build`) + `composer install` + nginx + PHP-FPM.
2. **Release:** `bash scripts/railway-db-setup.sh` (espera la DB y migra).
3. **Start:** `docker/entrypoint-railway.sh` (nginx en `$PORT`, PHP-FPM, migraciones de nuevo por si acaso, scheduler).

En Railway **no** configures Start Command. Si quedó `supervisord` o `php-fpm` a mano, borralo: tiene que usarse el `ENTRYPOINT` del `Dockerfile`.

Healthcheck: `GET /up` (timeout 120s).

Variables: ver `.env.railway`. Mínimo: `APP_KEY`, `APP_URL`, `DB_*` (o `DB_URL`) y `LOG_CHANNEL=stderr`.

## Backups y rollback

- [Backup MySQL](BACKUP.md) — `php artisan rodante:backup` / `scripts/backup-mysql.sh`
- [Runbook de rollback](ROLLBACK.md) — código vs restore, sin borrar historial de planilla

Antes de reconstruir o reemplazar el servicio MySQL, generar un `mysqldump` verificable. Una reconstrucción del contenedor no reemplaza el backup de la base ni garantiza que el volumen anterior siga asociado.
