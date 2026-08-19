# Roadmap — Rodante

# FUTURO absorbido 19 ago 2026 (pedido de producción): multiempresa, costos, QR, CSV, impresión, OT, recapadoras, avisos, Sanctum, inmutabilidad, numeración, assignments UNIQUE, Railway nginx+FPM. Ver `docs/DEPLOY.md`.


Convención:

- **P0** — rompe trazabilidad, seguridad o el flujo diario. Se hace primero.
- **P1** — el producto no se siente comercial sin esto.
- **P2** — mejora concreta, diferible.
- **FUTURO** — SaaS / venta; no entra en este ciclo.

Cada bloque de implementación debe: tests → cambio → tests → no tocar reglas 1–12.

---

## P0 — crítico

### P0.1 API con las mismas capabilities que la web

- Middleware `capability:write|retire` en `routes/api.php`.
- Validación de payload (no `$request->all()`).
- Test: Consulta recibe 403 en operate / incident / measurement / retire.

### P0.2 Auxilio no suma km al rotar (regla 5)

- En `relocateMounted`, setear `assignment.counts_km` y `openSegment.counts_km` según `is_spare` del destino.
- **No** cerrar el assignment (regla 6).
- Test: montar rodaje → rotar a auxilio → asentar km → `accumulated_km` no sube.

### P0.3 Corrección de odómetro (manual, no influye)

Decisión de producto (18 ago 2026): **no se recalculan km de cubiertas**.

1. La **lectura** se corrige (tabla `odometer_readings`).
2. Los `tire_movements` **no** se reescriben.
3. Los segmentos y `accumulated_km` **no** se tocan: el odómetro de hoy no pisa la operación ya asentada.
4. Queda rastro en `audit_logs`.

Fuera de alcance automático. Cualquier ajuste de km de cubierta es un proceso aparte.

### P0.4 Retorno reparación / reserva → STOCK (sin vida nueva)

- Flujo explícito: cubierta en `EN_REPARACION` o `RESERVA` → STOCK.
- Reutilizar `MovementType::FromRepair` / `FromReserva`.
- Reparación = **parche**: condición `Reparada`, misma vida. No recapar.
- No montar directo (sigue pasando por STOCK, regla 2).
- Test: pinchadura → retorno stock → install OK; `life_number` igual.

### P0.5 Planilla: definir `kmFmt` (o formateo local)

- Evitar `ReferenceError` en `fillFacts`.
- Test JS no hay; verificación manual + no romper tests PHP.

### P0.6 Login sin credenciales de demo en producción

- Prefill solo si `app()->environment('local')`.
- Railway: `SEED_DEMO` default documentado; en `.env.railway` `SEED_DEMO=0` comentado visible.

### P0.7 Tests de invariantes mínimos

- Un `tire_id` → una fila en `tire_current_locations`.
- Un assignment abierto (`ended_at` null) por cubierta montada.
- Cubierta `DE_BAJA` no se instala (ya existe; reforzar).
- Recap 403 para operario (HTTP).

---

## P1 — importante

### Dominio / backend

- Transacción única pinchadura/cambio (incidencia + operación).
- Cambio de configuración: pedir odómetro vía `OdometerService::record`.
- Recapado en UI solo si `canRetireOrRecap()`.
- Filtro real por flotas/bases del usuario (hoy el ABM no sirve).
- Usuario inactivo: middleware o check en `auth`.
- Throttle de login.
- `DomainException` → 422 global, mensaje humano.
- Índices: `accumulated_km`, `current_tread_min`, mediciones, segmentos.

### Ficha del neumático

- Timeline única (movimientos + incidencias + mediciones + cambios de vida), orden fecha desc.
- Mostrar: identidad, DOT si existe, vida, ubicación, vehículo, posición, km, mm por zona, alta, montaje, última intervención, alertas.
- Empty state del historial.
- Confirmación de baja con consecuencias.

### Dashboard

- Colas accionables: reparación, profundidad crítica, próximas a baja (umbral visible).
- KPI clicables a listados **ya filtrados** (varios ya lo hacen; completar tablas huérfanas).
- Ocultar o reordenar KPI según rol (operario ≠ consulta ≠ jefe).

### UX transversal

- Confirmación: baja, confirmar compra, cambio de config.
- Anti doble submit + disabled + “Guardando…”.
- Páginas 403/404/419/500 con identidad Rodante.
- Error por campo (`@error`, `aria-invalid`).
- Breadcrumbs.
- Búsqueda: número de cubierta **y** patente.
- Badges de **condición** además de status.
- Quitar resto lila; tokens navy/rojo/semánticos.

---

## P2 — mejora

- Paginación reportes km/consumo; no cargar todo el stock en la planilla.
- Empty states con CTA.
- Tablas → cards en móvil; tap ≥ 44 px; planilla usable en tablet (sin depender de drag + menú derecho).
- Labels visibles en ABM (no solo placeholder).
- Toasts / live region.
- Timezone `America/Argentina/Buenos_Aires`.
- Entrypoint Docker sin `|| true`; logs Railway stderr; startCommand.
- Factories de Tire/Unit; tests también en MySQL CI.
- Limpiar código muerto (`welcome`, `rotate` huérfano, kicker).
- Inter 800 o bajar weights a 700.
- Observabilidad: log de `DomainException`.

---

## FUTURO

No implementar en este ciclo:

- Multiempresa / tenant
- Costos (explícitamente fuera de v1)
- QR / códigos de barras / lectores
- Importación masiva / Excel
- PDF / órdenes de trabajo
- Recapadoras como actor
- Preventivo avanzado / notificaciones push
- Sanctum / API tokens para app de campo
- Columnas configurables de tablas
- Soft realtime (websockets)

---

## Orden de ejecución (fases del pedido)

| Fase | Contenido | Estado |
|---|---|---|
| A | Auditoría + `AUDIT.md` + este roadmap | Hecho |
| B | P0.1 API · P0.2 km auxilio al rotar · P0.5 kmFmt · P0.6 login · P0.7 invariantes | Hecho (18 ago 2026) |
| C | P0.4 retorno reparación + índices | Hecho (18 ago 2026) |
| D | Ficha timeline + confirmaciones | Hecho (18 ago 2026) |
| E | Design tokens + estados/badges | Hecho (18 ago 2026) |
| F | Dashboard colas | Hecho (18 ago 2026) |
| G | Búsqueda / breadcrumbs / formularios | Hecho (18 ago 2026) |
| H | Flota/base + pinchadura atómica + config/odómetro | Hecho (18 ago 2026) |
| I | Roles UI vs backend (recap oculto) | Hecho (18 ago 2026) |
| J | Tests de regresión ampliados | Hecho (18 ago 2026) |
| K–M | P2 performance / responsive / Docker | Hecho (19 ago 2026) |

---

## Criterio para cerrar un ítem P0

- Test que falla antes / pasa después (salvo P0.5 JS).
- No se altera recap vs repair, pasaje por stock, ni inmutabilidad de `tire_movements`.
- Consulta sigue sin escribir por HTTP **ni** por API.
- Auxilio no suma km (install **y** rotación).
