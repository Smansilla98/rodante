# Trazabilidad de neumáticos

Sistema de gestión y trazabilidad individual de cubiertas para flotas de camiones, semirremolques, tanques y bateas.

El historial es del **neumático** (`FH:01 Nº30363`), no de la patente. Tractor + semi es solo una vista. Semi/tanque/batea usan el odómetro del tractor acoplado en ese momento.

## Stack

Laravel 13, PHP 8.3, Blade, Tailwind 4, MySQL 8, PHPUnit, Docker.

## Inicio (Docker)

```bash
cd trazabilidad-neumaticos
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
cd /ruta/del/proyecto && npm install && npm run build
```

App: http://localhost:8093  
MySQL: `localhost:33062` / `trazabilidad` / `laravel` / `secret`

### Documentación

- [Manual de uso](docs/manual-de-uso.md) (también en la app: **Consulta → Ayuda**)
- [Propuesta de presupuesto para empresa de transportes](docs/presupuesto-empresa-transportes.md)

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
- 6X24 y 6X1 están parametrizados. 7X24 y 6X1X1 no se cargaron a propósito.

## Costos

Existen en el negocio. No se implementan en v1.
