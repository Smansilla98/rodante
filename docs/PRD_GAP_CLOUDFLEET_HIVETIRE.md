# PRD — Brecha funcional vs. cloudFleet y HiveTire (23 sep 2026)

Auditoría solicitada por el dueño del producto: relevar cloudFleet y HiveTire (competidores directos de gestión
de neumáticos de flota en LatAm) y completar en Rodante lo que falte. Investigación hecha por un subagente vía
búsqueda web (sin acceso a código fuente de ninguno de los dos — son productos privados; nada de lo de abajo se
obtuvo por ingeniería inversa). Todo lo marcado **[confirmado]** viene de una fuente pública citada; lo marcado
**[no confirmado]** es una función que existe mencionada en texto pero cuyo diseño visual/exacto no se pudo ver.

Convención de prioridad igual que `docs/ROADMAP.md`: **P0** rompe el flujo diario o es la brecha más visible,
**P1** el producto no se siente comercial sin esto, **P2** mejora diferible, **FUTURO** requiere hardware externo
o es un cambio de alcance de negocio, no de software.

## Fuentes

- cloudFleet: https://cloudfleet.com/ · https://cloudfleet.com/neumaticos ·
  https://soporte.cloudfleet.com/docs/cloudFleet/llantas/ (docs de soporte con detalle de flujo)
- HiveTire: https://www.hivetire.com/ · https://www.hivetire.com/ht-solucion/ ·
  https://cl.linkedin.com/company/hivetire-by-hivesystems

## Qué ya tiene Rodante (relevado en esta misma sesión, no es brecha)

Antes de listar lo que falta: Rodante ya cubre una base más amplia de lo que parecía a primera vista —
ciclo de vida completo del neumático, mapa de cubiertas interactivo por unidad (web con SVG + mobile), órdenes
de trabajo (recapado/reparación, incluyendo taller interno), compras e inventario con escaneo, alertas por
email, ~12 reportes CSV/Excel (incluido costo por km, costo por unidad, consumo), predicción de vida útil por
neumático con narrativa (`PredictiveWearService`, ya con IA opcional), auditoría, multi-empresa (`BelongsToCompany`
en todos los modelos de dominio) y una página pública de consulta rápida (`/campo`). Esto no estaba mapeado
explícitamente en un solo documento hasta ahora.

## P0 — hecho en esta sesión

### P0.1 Esquema de cubiertas: diagnóstico de rotación/alineación

cloudFleet tiene un motor de "Diagnóstico y sugerencias" **[confirmado]** que compara desgaste entre posiciones
para sugerir rotación, y diferencia interior/exterior para detectar desalineación. Rodante ya capturaba los
datos necesarios (`tire_measurement_readings` por zona, incluida `FLANCO_IZQ`/`FLANCO_DER`) pero no los cruzaba.

Implementado:

- `App\Services\TireDiagnosticService` — compara flanco izq/der de la última medición de cada cubierta
  (desalineación, umbral 1.5mm) y compara `current_tread_min` entre posiciones del mismo eje (rotación, umbral
  2.0mm). Sin dependencias externas, corre sobre datos ya existentes.
- `FleetUnit::tireDiagnostics()` — expone el resultado indexado por `position_id`, reutilizado por web y API.
- Web: `tire-sheet-unit.blade.php` → `tire-sheet-axle.blade.php` → `tire-box.blade.php` ahora reciben
  `diagnostics` y muestran una insignia "!" ámbar + el detalle en el tooltip. Leyenda actualizada.
- API: `GET /api/v1/units/{unit}/layout` ahora incluye `diagnostics` por posición.
- Mobile: `app/(tabs)/units/[id].tsx` — `PositionBox` muestra la misma insignia ámbar + motivo corto, y un
  ícono de info junto a "Mapa de cubiertas" explica el patrón (reutiliza `InfoTooltip`, el mismo componente
  agregado esta sesión para las OT).

No incluye: sugerencia de calibración de presión (Rodante no captura presión hoy, ver P1.1) ni reencauche/
reemplazo automático (ya cubierto por el `status` de `PredictiveWearService`, que no es parte de esta brecha).

## P1 — el producto no se siente comercial sin esto

### P1.1 Captura de presión de neumático (manual)

Tanto cloudFleet como HiveTire construyen buena parte de su "inteligencia" sobre presión, no solo profundidad.
Rodante no tiene ningún campo de presión hoy. Propuesta acotada a software (sin hardware, ver FUTURO):

- Migración: `psi` nullable en `tire_measurements` (una lectura por medición, no por zona — la presión es del
  neumático completo, a diferencia de la profundidad).
- `StoreTireMeasurementRequest` + `MeasurementService`: aceptar `psi` opcional.
- `TireDiagnosticService`: si `psi` está fuera de un rango configurable (a definir con el dueño del producto,
  cloudFleet no publica el umbral exacto), agregar el flag `PRESION` al mapa de cubiertas.
- Web/mobile: campo opcional en el formulario de medición ya existente (`neumaticos/{tire}/mediciones`).

Bloqueado por una decisión de negocio, no técnica: **¿de dónde sale el valor recomendado por medida/carga?**
(cloudFleet/HiveTire lo resuelven con un manómetro Bluetooth o TPMS — ver FUTURO). Sin eso, ANTES de
implementar habría que preguntarle al dueño del producto si quiere un umbral fijo simple (ej. avisar solo si
está <20% del valor cargado manualmente al registrar el neumático) o dejarlo fuera hasta tener el dato.

### P1.2 Exportar diagnóstico a PDF

cloudFleet exporta el informe de diagnóstico a PDF **[confirmado]**. Rodante ya tiene generación de PDF
(`resources/views/print/work-order.blade.php` para OT). Extender el mismo patrón a un
`GET /unidades/{unit}/diagnostico.pdf` con el resultado de `tireDiagnostics()` en una planilla imprimible —
reutiliza `TireDiagnosticService` tal cual, es agregar una vista + ruta, no lógica nueva.

## P2 — mejora concreta, diferible

### P2.1 Comparación de marcas/modelos por costo y vida útil

cloudFleet ofrece comparación entre marcas **[confirmado]**. Rodante ya tiene `reporte-costo-km.csv` y
`reporte-costo-unidad.csv` a nivel unidad/flota, pero no un corte por marca/modelo de neumático. Agregar un
reporte más (`reporte-comparativa-marcas.csv`) agrupando `tires` por `tire_brand_id`/`tire_model_id` con
promedio de `accumulated_km` / mm gastado y costo total de `cost_entries` — mismo patrón que los reportes
existentes en `ReportService`, sin modelo de dominio nuevo.

### P2.2 Filtro de reportes por proveedor/ruta

HiveTire filtra reportes por proveedor, ruta o región **[confirmado]**. Rodante ya filtra por flota/base en
varios reportes; "ruta/región" no es un concepto que exista en el dominio actual (no hay campo de ruta en
`fleet_units` ni en `tire_movements`). Diferido hasta confirmar si el dueño del producto lo necesita — agregar
un campo de dominio nuevo sin caso de uso confirmado sería adivinar alcance de negocio, no completar una
brecha de software.

## FUTURO — requiere hardware externo (fuera de alcance de este PRD)

Estas tres son el diferencial más fuerte que encontró la investigación, pero **ninguna es un cambio de
software de Rodante**: dependen de comprar/integrar hardware de terceros (manómetros Bluetooth, chips RFID,
sensores TPMS), negociar con un proveedor, y recién ahí construir la integración. No se avanza nada de esto sin
que el dueño del producto decida invertir en el hardware primero.

- **Medición con dispositivo Bluetooth** (cloudFleet [confirmado]): captura automática de profundidad/presión
  sin tipeo manual.
- **RFID embebido en el neumático** (HiveTire [confirmado]): lectura a distancia, control de entrada/salida,
  alerta de robo.
- **TPMS con sensores internos en tiempo real** (HiveTire [confirmado]): presión y temperatura en vivo, alerta
  automática por email, auto-cierre de incidente.

## Lo que la investigación NO pudo confirmar

Ninguno de los dos productos documenta públicamente: soporte offline en la app móvil, multi-empresa explícito,
ni asignación formal conductor-vehículo. No se agrega nada de esto a la brecha — no hay evidencia de que sea
una función real a igualar, y Rodante no tiene ese caso de uso planteado hoy.

## Estado

P0 implementado y verificado (`tsc --noEmit` 0 errores en mobile, `php -l` sin errores en los archivos PHP
tocados) — falta el smoke test contra producción una vez deployado. P1/P2 quedan documentados para priorizar
con el dueño del producto; P1.1 en particular necesita una decisión de negocio antes de tocar código.
