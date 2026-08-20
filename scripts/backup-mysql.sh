#!/usr/bin/env bash
# Backup MySQL de Rodante desde el host (Docker Compose) o con cliente local.
# Uso:
#   bash scripts/backup-mysql.sh
#   bash scripts/backup-mysql.sh /ruta/destino
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
export DOCKER_HOST="${DOCKER_HOST:-unix:///var/run/docker.sock}"
DEST="${1:-$ROOT/storage/app/private/backups}"
mkdir -p "$DEST"
STAMP="$(date +%Y%m%d-%H%M%S)"
FILE="$DEST/rodante-compose-${STAMP}.sql.gz"

if command -v docker >/dev/null 2>&1 && docker compose -f "$ROOT/docker-compose.yml" exec -T mysql true >/dev/null 2>&1; then
  echo "[backup] Dump vía contenedor mysql..."
  docker compose -f "$ROOT/docker-compose.yml" exec -T mysql \
    mysqldump -uroot -prootsecret --single-transaction --routines --triggers --hex-blob --no-tablespaces trazabilidad \
    | gzip -c > "$FILE"
else
  echo "[backup] Dump vía mysqldump local (DB_* del entorno)..."
  : "${DB_HOST:=127.0.0.1}"
  : "${DB_PORT:=33062}"
  : "${DB_DATABASE:=trazabilidad}"
  : "${DB_USERNAME:=laravel}"
  : "${DB_PASSWORD:=secret}"
  export MYSQL_PWD="$DB_PASSWORD"
  mysqldump --single-transaction --routines --triggers --hex-blob --no-tablespaces \
    -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" "$DB_DATABASE" | gzip -c > "$FILE"
fi

echo "[backup] OK → $FILE ($(wc -c < "$FILE") bytes)"
