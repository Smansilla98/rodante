# Backup de base de datos — Rodante

Motor soportado en producción: **MySQL 8**.

## Qué se respalda

Dump lógico completo (`mysqldump --single-transaction`) con rutinas y triggers, comprimido en `.sql.gz`.

Ubicación por defecto:

```text
storage/app/private/backups/rodante-<db>-<YYYYMMDD-HHMMSS>.sql.gz
```

No versionar dumps en git.

## Comandos

### Dentro de la app (contenedor `app` / Railway)

Requiere `mysqldump` en el PATH (incluido en `Dockerfile.fpm`).

```bash
php artisan rodante:backup --keep=14
```

### Desde el host con Docker Compose

```bash
bash scripts/backup-mysql.sh
```

## Automatización

En `routes/console.php` el scheduler corre el backup a las **03:15** (timezone de la app).

En el contenedor de producción, Supervisor ejecuta `php artisan schedule:work` junto a nginx/php-fpm.

En Railway también podés disparar un cron one-off:

```bash
railway run php artisan rodante:backup --keep=14
```

## Restore (desarrollo)

Probado contra Docker Compose (MySQL 8): backup → wipe de una fila marcador → restore → la fila vuelve.

```bash
# Compose
bash scripts/restore-mysql.sh storage/app/private/backups/archivo.sql.gz

# O artisan (misma DB del .env; requiere cliente mysql en el contenedor)
php artisan rodante:restore storage/app/private/backups/archivo.sql.gz --force
```

Después del restore, si el dump es más viejo que el código:

```bash
php artisan migrate --force
```

## Retención

`--keep=14` deja los 14 `.sql.gz` más recientes en el directorio y borra el resto.

## Railway / volumen

Los dumps viven en el filesystem del contenedor. Si el disco es efímero, montá un volumen o copiá el `.sql.gz` a un bucket (S3/R2) después del backup. El comando deja el archivo listo para `scp`/`railway run` + descarga.
