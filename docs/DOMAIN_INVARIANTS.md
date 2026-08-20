# Domain invariants — Rodante

Fuente operativa de las reglas críticas. Complementa `INVARIANTES.md` con refuerzos de base y huecos conocidos.

## Estado actual vs historia

| Concepto | Mutabilidad | Protección |
|---|---|---|
| `tire_current_locations` | Estado actual | Unique `tire_id` + `(unit_id, position_id)` |
| `tires.status` / `condition` / `accumulated_km` | Estado derivado | Services + integridad |
| `tire_movements` | Historia inmutable | Observer + override model; **sin trigger DB** |
| `tire_assignments` / segmentos | Historia + abierto | Unique `open_tire_id` / `open_assignment_id` + CHECK MySQL |
| `tire_lifecycles` | Historia | Unique `(tire_id, life_number)` |
| `odometer_readings` | Editable con permiso | Corrección realinea segmentos; no reescribe movimientos |
| `tire_number_changes` | Historia de corrección | Motivo + usuario + fechas |

## Reglas 1–15 (resumen)

Ver también `docs/INVARIANTES.md`. En este documento:

9. **Un assignment abierto.** Unique nullable `open_tire_id` + observer. MySQL: `chk_assignment_open_consistency`. Integridad detecta `OPEN_ASSIGNMENT_NULL_KEY`.
13–14. Posiciones solo del catálogo de configuración de la unidad.
15. Estados imposibles: pantalla Integridad + `rodante:integrity`.

## Numeración concurrente

- Documentos (`OC-`, `OT-`): `document_counters` + `lockForUpdate`.
- Números individuales: mismo contador (`tire_individual`) con reserva de rango al confirmar compra.

## Odómetro

Corregir una lectura recalcula `start_odometer` / `end_odometer` / `km_delta` de segmentos que usaban el valor viejo y refresca `accumulated_km`. Los `tire_movements.km_delta` **no** se reescriben (inmutabilidad).

## Qué aún no está blindado en DB

- Triggers anti-UPDATE/DELETE en `tire_movements`.
- CHECK de segmentos en SQLite (tests confían en app + integridad).
- Inventario físico con diferencias (proceso de producto, no solo listado).

## Inventario físico

Flujo: abrir sesión por base (snapshot STOCK/RESERVA/EN_REPARACION) → conteo (nº o QR) → revisión → cierre.

- **Faltantes / montadas:** solo auditoría (no baja ni desmonte automático).
- **Wrong base / sobrante stockable:** jefe/admin puede aplicar `TRANSFER_BASE` al cerrar.
- Una sesión activa por base. Numeración `INV-` vía `document_counters`.
