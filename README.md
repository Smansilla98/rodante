# Rodante

Gestión inteligente de neumáticos para flotas. Sistema de trazabilidad individual de cubiertas para camiones, semirremolques, tanques y bateas.

Repositorio: [github.com/Smansilla98/rodante](https://github.com/Smansilla98/rodante)

El historial es del **neumático** (`FH:01 Nº30363`), no de la patente. Tractor + semi es solo una vista. Semi/tanque/batea usan el odómetro del tractor acoplado en ese momento.

## Stack

Laravel 13, PHP 8.3, Blade, Tailwind 4, MySQL 8, PHPUnit, Docker.

## Inicio (Docker)

```bash
cd rodante
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
cd /ruta/del/proyecto && npm install && npm run build
```

App: http://localhost:8093  
MySQL: `localhost:33062` / `trazabilidad` / `laravel` / `secret`

### Documentación

- [Auditoría técnica](docs/AUDIT.md)
- [Roadmap P0/P1/P2](docs/ROADMAP.md)
- [Manual de uso](docs/manual-de-uso.md) (también en la app: **Consulta → Ayuda**)
- [Propuesta de presupuesto para empresa de transportes](docs/presupuesto-empresa-transportes.md)
- QA por rol: `php artisan qa:roles` (logs en `storage/logs/qa/` y `docs/qa/`)
- Railway: variables en [`.env.railway`](.env.railway) + release [`scripts/railway-db-setup.sh`](scripts/railway-db-setup.sh)
- **Base de datos:** el producto usa **MySQL 8** (local y Railway recomendado). PostgreSQL también está soportado vía el mismo script.

### Deploy Railway (resumen)

1. Servicio web + plugin **MySQL** (recomendado) o **PostgreSQL**.
2. Pegá [`.env.railway`](.env.railway) en Variables (Raw Editor) y dejá activo el bloque del motor que uses.
3. Generá `APP_KEY` con `php artisan key:generate --show` y pegala.
4. Poné `APP_URL` con el dominio público (`https://….up.railway.app`).
5. Release command (también en `railway.toml`): `bash scripts/railway-db-setup.sh`  
   Migra y carga la demo (`admin` / `password`, etc.). Para solo migrar: variable `SEED_DEMO=0`.

### Usuarios demo

| Usuario    | Contraseña | Rol            |
|------------|------------|----------------|
| `admin`    | `password` | Administrador  |
| `jefe`     | `password` | Jefe de sector |
| `logistica`| `password` | Logística      |
| `operario` | `password` | Operario       |
| `consulta` | `password` | Consulta       |

## Tests

```bash
docker compose exec app php artisan test
```

## Reglas que no se negocian

- Un neumático, una ubicación actual.
- Todo retiro pasa por STOCK, aunque sea de pasaje.
- Recapado abre vida nueva. Reparación no.
- Auxilio no suma km.
- Rotación no cierra el periodo de km.
- Eventos históricos inmutables.
- Las configuraciones de ejes son las del catálogo: tractores `4X2`, `4X4`, `6X2`, `6X2-P`, `6X4`, `6X6`, `8X2`, `8X4`, `8X8`, `10X4`, `10X6`; acoplados `1E`–`5E` (simple/dual) y `3E-1S2D`. No existen `6X24`, `6X1`, `7X24` ni `6X1X1`.

## Costos

Existen en el negocio. No se implementan en v1.
