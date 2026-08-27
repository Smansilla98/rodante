#!/usr/bin/env bash
# =============================================================================
# Rodante — setup de base de datos en Railway (release / one-off)
# =============================================================================
# Qué hace:
#   1) Detecta MySQL o PostgreSQL desde las variables de entorno
#   2) Normaliza DB_CONNECTION / DB_URL para Laravel
#   3) Espera a que la DB responda
#   4) Corre migraciones
#   5) Carga catálogo + demo (usuarios admin/password, flota de ejemplo)
#
# Uso en Railway:
#   Deploy → Settings → Deploy → Custom Start / Release Command:
#     bash scripts/railway-db-setup.sh
#
#   O como one-off:
#     railway run bash scripts/railway-db-setup.sh
#
# Motor recomendado (producción actual del proyecto): MySQL 8
# Alternativa soportada: PostgreSQL (plugin Postgres de Railway)
#
# Variables útiles:
#   SEED_DEMO=1   — corre db:seed --force (usuarios demo). Default.
#   SEED_DEMO=0   — solo migraciones
#   DB_WAIT_SECONDS=90 — timeout de espera a la DB
# =============================================================================

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

log() { printf '[railway-db] %s\n' "$*"; }
die() { printf '[railway-db] ERROR: %s\n' "$*" >&2; exit 1; }

SEED_DEMO="${SEED_DEMO:-1}"
DB_WAIT_SECONDS="${DB_WAIT_SECONDS:-90}"

# --- Normalizar URL de conexión (Railway suele inyectar varias) --------------
pick_url() {
  local candidate
  for candidate in \
    "${DB_URL:-}" \
    "${DATABASE_URL:-}" \
    "${MYSQL_URL:-}" \
    "${MYSQL_PRIVATE_URL:-}" \
    "${POSTGRES_URL:-}" \
    "${POSTGRES_PRIVATE_URL:-}"
  do
    if [[ -n "${candidate}" ]]; then
      printf '%s' "${candidate}"
      return 0
    fi
  done
  return 1
}

detect_driver_from_url() {
  local url="$1"
  case "${url}" in
    mysql://*|mysql2://*) printf 'mysql' ;;
    postgres://*|postgresql://*) printf 'pgsql' ;;
    *) return 1 ;;
  esac
}

# Si solo vienen variables desglosadas del plugin Postgres
if [[ -z "${DB_CONNECTION:-}" && -n "${PGHOST:-}" ]]; then
  export DB_CONNECTION=pgsql
  export DB_HOST="${DB_HOST:-$PGHOST}"
  export DB_PORT="${DB_PORT:-${PGPORT:-5432}}"
  export DB_DATABASE="${DB_DATABASE:-${PGDATABASE:-railway}}"
  export DB_USERNAME="${DB_USERNAME:-${PGUSER:-postgres}}"
  export DB_PASSWORD="${DB_PASSWORD:-${PGPASSWORD:-}}"
fi

# Si solo vienen variables desglosadas del plugin MySQL
if [[ -z "${DB_CONNECTION:-}" && -n "${MYSQLHOST:-}" ]]; then
  export DB_CONNECTION=mysql
  export DB_HOST="${DB_HOST:-$MYSQLHOST}"
  export DB_PORT="${DB_PORT:-${MYSQLPORT:-3306}}"
  export DB_DATABASE="${DB_DATABASE:-${MYSQLDATABASE:-railway}}"
  export DB_USERNAME="${DB_USERNAME:-${MYSQLUSER:-root}}"
  export DB_PASSWORD="${DB_PASSWORD:-${MYSQLPASSWORD:-}}"
fi

if URL="$(pick_url)"; then
  export DB_URL="$URL"
  if DRIVER="$(detect_driver_from_url "$URL")"; then
    export DB_CONNECTION="$DRIVER"
  fi
fi

# Default del producto: MySQL (local Docker + .env.railway)
export DB_CONNECTION="${DB_CONNECTION:-mysql}"

case "${DB_CONNECTION}" in
  mysql|mariadb|pgsql|postgres|postgresql) ;;
  *) die "DB_CONNECTION no soportado: ${DB_CONNECTION} (usá mysql o pgsql)" ;;
esac

# Laravel usa "pgsql", no "postgres"
if [[ "${DB_CONNECTION}" == "postgres" || "${DB_CONNECTION}" == "postgresql" ]]; then
  export DB_CONNECTION=pgsql
fi

log "Driver: ${DB_CONNECTION}"
if [[ -n "${DB_URL:-}" ]]; then
  # Ocultar password en el log
  SAFE_URL="$(printf '%s' "$DB_URL" | sed -E 's#://([^:/@]+):([^@]+)@#://\1:***@#')"
  log "DB_URL: ${SAFE_URL}"
else
  log "Host: ${DB_HOST:-?}  DB: ${DB_DATABASE:-?}  User: ${DB_USERNAME:-?}"
fi

command -v php >/dev/null 2>&1 || die "php no está en el PATH"
[[ -f artisan ]] || die "no se encontró artisan en ${ROOT}"
[[ -f vendor/autoload.php ]] || die "faltan dependencias Composer (vendor/). Corré composer install en el build."

# --- Esperar a que la DB acepte conexiones ----------------------------------
log "Esperando conexión a la base (máx. ${DB_WAIT_SECONDS}s)..."
deadline=$((SECONDS + DB_WAIT_SECONDS))
until php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    Illuminate\Support\Facades\DB::connection()->getPdo();
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
' >/tmp/rodante-db-wait.err 2>&1; do
  if (( SECONDS >= deadline )); then
    cat /tmp/rodante-db-wait.err >&2 || true
    die "timeout esperando la base de datos"
  fi
  sleep 2
done
log "Conexión OK"

# --- Migrar + demo ----------------------------------------------------------
log "Migrando (php artisan migrate --force)..."
php artisan migrate --force

if [[ "${SEED_DEMO}" == "1" || "${SEED_DEMO}" == "true" || "${SEED_DEMO}" == "yes" ]]; then
  log "Cargando catálogo y demo (php artisan db:seed --force)..."
  php artisan db:seed --force
  log "Demo lista. Usuarios: admin / jefe / logistica / operario / consulta — password"
else
  log "SEED_DEMO=${SEED_DEMO}: se omitió el seed"
fi

log "Listo."
