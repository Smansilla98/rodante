#!/usr/bin/env bash
# Restaura un dump .sql.gz a la MySQL de Docker Compose (pisa la DB).
# Uso:
#   bash scripts/restore-mysql.sh storage/app/private/backups/archivo.sql.gz
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
export DOCKER_HOST="${DOCKER_HOST:-unix:///var/run/docker.sock}"
FILE="${1:-}"
[[ -n "$FILE" && -f "$FILE" ]] || { echo "Uso: $0 ruta/al/backup.sql.gz" >&2; exit 1; }

echo "[restore] ATENCIÓN: se pisa la base trazabilidad del compose."
docker compose -f "$ROOT/docker-compose.yml" exec -T mysql \
  mysql -uroot -prootsecret -e "DROP DATABASE IF EXISTS trazabilidad; CREATE DATABASE trazabilidad CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON trazabilidad.* TO 'laravel'@'%'; FLUSH PRIVILEGES;"

if [[ "$FILE" == *.gz ]]; then
  gzip -dc "$FILE" | docker compose -f "$ROOT/docker-compose.yml" exec -T mysql mysql -uroot -prootsecret trazabilidad
else
  docker compose -f "$ROOT/docker-compose.yml" exec -T mysql mysql -uroot -prootsecret trazabilidad < "$FILE"
fi

echo "[restore] OK. Corré php artisan migrate --force si el dump es anterior al código."
