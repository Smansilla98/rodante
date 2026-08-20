# Deploy — Rodante

## Local (Docker Compose)

HTTP lo sirve **nginx** (puerto `8093`) y habla con **PHP-FPM** del servicio `app`.

```bash
SEED_DEMO=1 docker compose up --build
```

Demo: http://localhost:8093 — usuarios `admin` / `jefe` / `logistica` / `operario` / `consulta`, contraseña `password`.

`SEED_DEMO=1` corre `php artisan db:seed` en el entrypoint del contenedor `app` (después de migrar). El seeder es idempotente: si ya existe la unidad `HKH 448` no duplica el lote, pero sí asegura usuarios y recapadora demo.

## Railway (producción)

Un solo contenedor (`Dockerfile.fpm`):

1. El **release command** (`scripts/railway-db-setup.sh`) espera la DB, migra y opcionalmente hace seed (`SEED_DEMO=1`).
2. El **start command** es `supervisord`, que arranca:
   - `php-fpm -F` en `127.0.0.1:9000`
   - `nginx` escuchando `$PORT` (Railway lo inyecta) y haciendo FastCGI a FPM

**PHP-FPM solo no atiende HTTP público.** Si el start command vuelve a `php-fpm -F` sin nginx/Caddy/Apache delante, Railway no va a servir la app.

Healthcheck: `GET /up`.

Variables: ver `.env.railway`.

## Backups y rollback

- [Backup MySQL](BACKUP.md) — `php artisan rodante:backup` / `scripts/backup-mysql.sh`
- [Runbook de rollback](ROLLBACK.md) — código vs restore, sin borrar historial de planilla
