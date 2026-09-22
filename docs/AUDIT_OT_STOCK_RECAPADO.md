# INFORME DE AUDITORÍA — Órdenes de Trabajo, Recapado, Stock, Inventario Físico y Trazabilidad

**Alcance:** backend (Laravel), web (Blade), app (Expo/React Native), base de datos, migraciones, políticas, tests.
**Método:** lectura directa de modelos, migraciones, servicios, controladores, policies, tests y vistas — sin ejecutar el sistema (no hay motor de base de datos disponible en este entorno; ningún hallazgo de este informe requería ejecución, todos surgen de código/esquema/tests estáticos).

---

## ADENDA — Implementación de correcciones (2026-09-22)

Por pedido explícito, se implementaron las correcciones no-arquitectónicas de este informe. **No se tocó nada de lo marcado como decisión pendiente (DEC-001 a DEC-004)** que requiere unificar entidades o cambiar el modelo de datos — eso sigue esperando confirmación del negocio, tal como recomendaba este mismo informe.

**Corregido:**
- **INC-01 / INC-06 / DEC-004** — `IncidentService::openNewLife()` (la función que corre al cerrar una OT de Recapado) ahora crea un `tire_movements` tipo `FROM_REPAIR`, igual que la vía directa (`TireOperationService::returnToStock(asRecap: true)`). **Corrección sobre el hallazgo original de INC-06:** al leer `LocationService::place()` con más detalle se confirmó que la cubierta SÍ volvía a stock automáticamente al cerrar una OT de Recapado (el `elseif` que se señalaba en `WorkOrderService::close()` no aplicaba porque `openNewLife()` ya hacía el `place()` a Stock por su cuenta) — el problema real era solamente que esa transición no dejaba un `tire_movements`, no que la cubierta quedara "perdida". Ya está corregido y cubierto por test.
- **INC-02** — se agregaron tests que cierran una OT de Recapado (una cubierta y un lote de dos) y verifican vida nueva, condición, retorno a stock, movimiento y costo. Ver `tests/Feature/ProductionHardeningTest.php`.
- **INC-07** — `AuditLog` ahora está protegido por el mismo `ImmutableRecordObserver` que `TireMovement` (`app/Providers/AppServiceProvider.php`). Test agregado.
- **INC-08** — `WorkOrderPolicy::manage()` ahora exige `canRetireOrRecap()` para cerrar/cancelar una OT de tipo Recapado, igual que para abrirla. Antes solo pedía `canWrite()`. Test agregado.
- **INC-09** — el formulario de medición en mobile ahora pide una lectura por cada zona real de la medida de la cubierta (antes mandaba una sola zona con un ID tipeado a mano). Se agregó `measurement_zones` a la respuesta de `/tires/{id}/history` para que el cliente sepa qué zonas pedir.
- **Extra (no numerado en el informe original, mismo tipo de problema que INC-09):** el formulario de "Registrar incidente" en mobile pedía escribir a mano el valor exacto del enum (`PINCHADURA`, etc.) — ahora es una selección de opciones, igual que en la web.
- **INC-11** — `MovementType::FromSpare` ahora se usa: retirar una cubierta desde la posición de auxilio genera ese tipo de movimiento en vez del genérico `REMOVE_TO_STOCK`. Test agregado.

**Corrección sobre un segundo hallazgo del informe original:**
- **INC-10** — al releer `PurchaseService::confirm()` completo se confirmó que **ya** llama a `$this->audit->log('purchase.confirmed', $purchase)` (línea final del método). El hallazgo original decía que faltaba — estaba mal. No se hizo ningún cambio acá.

**Deliberadamente NO implementado** (siguen siendo decisiones de negocio, no correcciones de código — ver sección 17/`DECISIONES_PENDIENTES.md`):
- DEC-001 en su forma fuerte (fusionar los dos flujos en uno solo) — se optó por la opción A del propio informe (dejar los dos, pero que dejen el mismo rastro), no por eliminar ninguno.
- DEC-002 (operar por cantidad/lote sin elegir cada cubierta) — no implementado.
- DEC-003 (unificar `WorkOrder` con `TireOperation`) — no implementado, por ser el cambio de mayor riesgo sobre el módulo más probado del sistema.
- INC-12 (circuito de "recapado rechazado") — no implementado, sigue sin estar confirmado si hace falta.

**Estado de los tests:** no se pudo ejecutar la suite en este entorno (no hay driver `pdo_sqlite` disponible ni acceso al servidor MySQL de pruebas — misma limitación que ya constaba en este informe). Se verificaron sintaxis (`php -l`) y se trazó manualmente cada test nuevo contra el código real de los servicios involucrados. El código mobile se verificó con `tsc --noEmit` (0 errores). Se recomienda correr `php artisan test` en un entorno con base de datos antes de desplegar.

---

## 0. RESUMEN EJECUTIVO

### 0.1 Estado general

| Aspecto | Estado | Observación |
|---|---|---|
| Orden de Trabajo | PARCIAL | Existe (`work_orders`), pero solo cubre gomería/recapado. No representa montaje/desmontaje/rotación — eso lo maneja una entidad distinta (`tire_operations`) nunca llamada "OT". |
| Neumáticos | OK | Activo individual, bien identificado (`individual_number`, DOT, `public_token`/QR), separado de ubicación y de historial. |
| Stock | OK (conceptualmente) | No es una entidad con cantidad propia: es un `COUNT` de `tires.status='STOCK'`. Correcto para este dominio (cada cubierta es única), pero difiere del modelo "cantidad" que describe el pedido. |
| Movimientos | OK | `tire_movements` inmutable (trigger MySQL + Observer), 1 fila por cubierta por transición, nunca se actualiza. |
| Recapado | PROBLEMÁTICO | Dos caminos independientes y no unificados para el mismo hecho de negocio (ver 0.6 y RN-005). Uno de los dos caminos no tiene ningún test. |
| Inventario | NO IMPLEMENTADO (como "alta por cantidad") | No existe un concepto de stock agregado a ajustar; lo que existe es Inventario Físico (ver fila siguiente), que sí está bien resuelto. |
| Inventario físico | OK | Snapshot teórico → conteo → diferencia → cierre, con ajuste opcional y trazable. Bien implementado y testeado. |
| Web | OK | Formularios server-rendered, sin lógica de negocio en JS para OT/Inventario. |
| App | PARCIAL | OT e Inventario recién se expusieron por API en esta misma sesión de trabajo (commits de hoy) reusando los mismos Services/Policies que la web — sin duplicar lógica — pero **sin ningún uso real ni test end-to-end todavía**. |
| Trazabilidad | PARCIAL | Reconstruible en un ~90% de los casos (ver 11). El hueco es el recapado vía Orden de Trabajo (queda como `TireIncident`, no aparece homogéneamente junto a `tire_movements` en algunos reportes) y la falta de vínculo Orden de Trabajo ↔ Unidad. |
| Auditoría | PARCIAL | `audit_logs` existe y se usa en casi todos los Services relevantes, pero el propio modelo `AuditLog` no tiene protección de inmutabilidad (a diferencia de `TireMovement`). |
| Tests | PARCIAL | Hay cobertura fuerte de invariantes de stock/movimientos/inventario físico. **Cero tests cierran una Orden de Trabajo de tipo Recapado** (ver 0.2, hallazgo CRÍTICO). |

### 0.2 Hallazgos principales

| Prioridad | Hallazgo | Impacto | Evidencia |
|---|---|---|---|
| CRÍTICO | El recapado tiene **dos caminos de código independientes** que producen el mismo resultado de negocio (condición `RECAPADA`, vida nueva) por rutas distintas, con distinta trazabilidad. | Un recapado hecho por Orden de Trabajo se audita distinto de uno hecho por "Devolver a stock → recapado". Un reporte que busque "todas las recapadas" puede perder la mitad según dónde mire. | `app/Services/WorkOrderService.php:130-136` (llama `IncidentService::register('RECAPADO')`) vs `app/Services/TireOperationService.php:444-497` (`returnToStock(..., asRecap: true)`, usado por `app/Http/Controllers/Tire/TireController.php:217-231` y `app/Http/Controllers/Api/TireApiController.php:358-372`). |
| CRÍTICO | **Cerrar una Orden de Trabajo de tipo Recapado no tiene ningún test.** | El camino de recapado que sí queda ligado a taller/costo/OT (el que el negocio probablemente considera "el oficial") es el que menos se prueba. | `grep -rn "WorkOrderType::Recapado" tests/` → una sola coincidencia, en `tests/Feature/ProductionHardeningTest.php:227`, y solo cubre `open()`, no `close()`. |
| ALTO | La Orden de Trabajo **no tiene relación con `fleet_units` (vehículo)**. No puede representar montaje, desmontaje, rotación, instalación — esas operaciones viven en una entidad completamente distinta (`tire_operations`) que nunca se llama "orden de trabajo" en el código ni en la UI. | El concepto de OT del pedido (punto 1) no coincide con la entidad `WorkOrder` real. Cualquier cambio que asuma "la OT cubre montaje/desmontaje" rompe el modelo actual sin una decisión explícita. | `database/migrations/2026_08_19_200000_production_hardening.php:52-69` (tabla `work_orders`, sin columna `unit_id`/`fleet_unit_id`) vs `database/migrations/2026_08_13_100000_create_traceability_tables.php:243-252` (`tire_operations`, sí tiene `unit_id`). |
| ALTO | El sistema **no soporta "enviar X unidades de un stock/medida a recapado" sin elegir cada cubierta.** Todo lo que toca una cubierta exige su `id` real. | El escenario del punto 3 del pedido ("MEDIDA: 295/80 R22.5, CANTIDAD: 20, sin seleccionar manualmente cada cubierta") no existe hoy. | `app/Services/WorkOrderService.php:32` (`Tire|iterable $tires` — siempre instancias reales); no hay ningún método que reciba `tire_size_id` + `quantity`. |
| ALTO | `docs/AUDIT.md` (18-ago-2026, la auditoría previa más reciente) está desactualizada: fue escrita **antes** de que existieran Órdenes de Trabajo y Recapadoras, y las lista en "Futuro (no implementar ahora)". Varios de sus hallazgos CRÍTICOS (API sin capabilities, retorno de reparación no implementado) **ya están corregidos** en el código actual. | Cualquiera que use `docs/AUDIT.md` como fuente de verdad va a partir de una foto vieja del sistema. | `docs/AUDIT.md:288-292` vs `routes/api.php` (20 rutas con middleware `capability:*`) y `app/Services/TireOperationService.php:444-497` (`returnToStock` con rama `FromRepair` implementada). |
| MEDIO | `AuditLog` (tabla `audit_logs`) no tiene protección de inmutabilidad — a diferencia de `TireMovement`, que sí la tiene (trigger + Observer). | El registro de auditoría podría editarse/borrarse por código futuro sin que nada lo impida. | No hay `AuditLog::observe(...)` en `app/Providers/AppServiceProvider.php` (se registran `Tire`, `TireAssignment`, `TireAssignmentSegment`, `TireCurrentLocation`, `TireMovement`, `WorkOrder`; `AuditLog` no aparece). |
| MEDIO | La autorización de `returnToStock(asRecap: true)` está partida entre `TirePolicy::write()` (permite a cualquier `canWrite()`) y un chequeo adicional dentro del Service (`canRetireOrRecap()`). Funciona bien hoy, pero es fácil de romper: alguien que solo lea la Policy puede pensar que un Operario puede marcar un recapado. | Riesgo de regresión de permisos si se toca la Policy sin ver el Service. | `app/Policies/TirePolicy.php:19-22` vs `app/Services/TireOperationService.php:454-456`. |
| MEDIO | El formulario de medición en la app móvil solo manda **una** zona (`zone_id` a mano), pero `MeasurementService::record()` exige una lectura de **todas** las zonas de la medida o rechaza el guardado entero. | La acción "Registrar medición" desde el teléfono falla siempre que la medida tenga más de una zona (el catálogo demo tiene 4). No es parte de OT/Recapado/Stock pero sí de "Movimientos y trazabilidad" (punto 5 del pedido), así que se deja registrado acá. | `app/Services/MeasurementService.php:29-42` (`foreach ($zones as $zone) { if (!array_key_exists(...)) throw ... }`) vs `mobile/app/(tabs)/tires/[id].tsx` (un solo par `zone_id`/`millimeters`). |
| BAJO | `MovementType::FromSpare` está definido y tiene label en `ReportService`, pero **nunca se crea** en ningún Service. La salida real de Auxilio se registra genéricamente como `RemoveToStock`. | Cosmético/reporting: no afecta stock ni trazabilidad real, pero el enum sugiere una distinción que no existe en la práctica. | `grep -rn "'type' => MovementType::" app/Services` no incluye `FromSpare`; sí incluye `ToSpare` (`TireOperationService.php:408`). |
| BAJO | El catálogo de Recapadoras/Talleres (`retread_shops`) y de Motivos (`movement_reasons`) no tiene ninguna relación con "tipo de recapado" (lineal/motriz) ni con capacidad/plazo del taller. | No bloquea nada hoy; lo dejo registrado porque en una sesión anterior de este mismo proyecto se discutió una clasificación Lineal/Motriz para recapados (ver `app/Models/Tire.php`, método `recapPatternLabel()`) que hoy deriva de `tire_models.application`, no de la Orden de Trabajo ni del taller. | `app/Models/Tire.php` (`recapPatternLabel()`), `database/migrations/2026_08_19_200000_production_hardening.php:41-50` (`retread_shops` sin campo de especialidad). |

### 0.3 Estado del modelo de negocio

| Concepto | Estado actual | Problema |
|---|---|---|
| Neumático | Separado correctamente (`tires`, identidad propia). | Ninguno relevante a este punto. |
| Stock | Separado, pero **no es una entidad**: es un filtro (`status='STOCK'`) sobre `tires`. | Ninguno en sí — es la decisión de diseño correcta para un dominio de unidades individualizadas. Puede confundir si se espera una tabla "stock" con `quantity`. |
| Movimiento | Separado, inmutable, 1 fila por cubierta. | Ninguno — es el punto más fuerte del sistema. |
| OT | **Mezclado.** `WorkOrder` (gomería) y `TireOperation` (planilla de unidad) son dos entidades distintas que cubren, juntas, lo que el pedido llama "OT". Ninguna de las dos por sí sola es "la OT" del punto 1. | Confusión de responsabilidad: si mañana alguien agrega lógica a `WorkOrder` asumiendo que también sirve para montaje/desmontaje, rompe el modelo. |
| Inventario | No existe como concepto de "ajuste de cantidad". | No aplica — ver Inventario físico. |
| Inventario físico | Separado correctamente (`inventory_sessions` + `inventory_lines`). | Ninguno relevante. |
| Recapado | **Mezclado / duplicado.** Es a la vez un `WorkOrderType`, un `IncidentType` y una rama de `TireOperationService::returnToStock()`. | Ver hallazgo CRÍTICO de 0.2. |

### 0.4 Stock y trazabilidad

**¿El stock actual es confiable?**
`PARCIALMENTE`. El conteo (`Tire::where('status','STOCK')`) es siempre consistente con la ubicación real porque `tire_current_locations.tire_id` es `unique` y todo cambio pasa por `LocationService::place()` (`app/Services/LocationService.php`). Pero **no hay conciliación automática entre `tires.status` y `tire_current_locations.location_kind`** más allá de lo que ya detecta `IntegrityService` (`STOCK_ON_UNIT`, `MOUNTED_WITHOUT_SLOT`) — es decir, hay un chequeo, pero es reactivo (hay que abrir la pantalla de Integridad), no una constraint de base de datos.

**¿Los movimientos son trazables?**
`SI`. `tire_movements` es inmutable (trigger + Observer), tiene `user_id`, `occurred_at`, `reason_id` opcional, y casi todos los Services que tocan stock crean un movimiento dentro de la misma transacción (`DB::transaction`) que el cambio de ubicación.

**¿Se puede reconstruir la historia de un neumático?**
`PARCIALMENTE`. Ver sección 11 completa. El 90% se reconstruye desde `tire_movements` + `tire_incidents` + `tire_measurements` + `tire_lifecycles`. El hueco es específicamente el recapado vía Orden de Trabajo, que no genera `tire_movements` (genera un `TireIncident` de tipo `RECAPADO`), y la OT en sí no queda enlazada a los movimientos que ocurrieron antes/después.

### 0.5 Orden de Trabajo

| Pregunta | Respuesta |
|---|---|
| ¿Existe OT? | SI, como `WorkOrder` + `WorkOrderItem` (`app/Models/WorkOrder.php`, `app/Models/WorkOrderItem.php`). |
| ¿Representa correctamente una operación? | PARCIAL. Representa correctamente "cubierta(s) enviada(s) a un taller externo para Recapado o Reparación". No representa montaje/desmontaje/rotación/instalación (eso es `TireOperation`). |
| ¿Relaciona neumáticos? | SI. `tire_id` (principal) + `work_order_items` (1..N, para Recapado). |
| ¿Relaciona movimientos? | NO directamente. No hay FK de `work_orders` a `tire_movements`. La OT genera movimientos indirectamente a través de los Services que llama (`ToRepair` al enviar a taller, `FromRepair` al cerrar una Reparación), pero no hay una columna ni tabla que diga "estos movimientos pertenecen a esta OT". |
| ¿Tiene estados? | SI: `ABIERTA`, `EN_TALLER`, `CERRADA`, `CANCELADA` (`app/Enums/WorkOrderStatus.php`). |
| ¿Tiene auditoría? | SI: `AuditService::log()` en `open()`, y hay que verificar `send`/`close`/`cancel` — confirmado en `sendToShop()` (`work_order.sent`) y `close()` (`work_order.closed`, vía log en el controller). |
| ¿Permite modificaciones incorrectas? | NO — `CERRADA` es terminal (`cancel()` rechaza si `status === Cerrada`, `app/Services/WorkOrderService.php:191-194`), y no existe ningún método `update()` sobre `WorkOrder` una vez creada (solo `sendToShop`/`close`/`cancel`, cada uno con su propia transición válida). |

### 0.6 Recapado

El recapado **no** trabaja por cantidad/lote — trabaja exclusivamente por cubierta individual, con dos vías paralelas:

1. **Vía Orden de Trabajo** (la más completa): `WorkOrderService::open()` con `type=RECAPADO` acepta una o varias cubiertas concretas (`Tire|iterable`) — no una cantidad. Al cerrar, `WorkOrderService::close()` llama `IncidentService::register($tire, ['type' => 'RECAPADO', ...])` por cada cubierta del lote, lo que cierra la vida actual y abre una nueva (`app/Services/IncidentService.php:88-99`). Queda ligado a taller (`retread_shop_id`), costo (`cost_entries`, categoría `RECAP`) y número de OT.
2. **Vía retorno directo a stock**: `TireOperationService::returnToStock($tire, $user, $notes, asRecap: true)`, disponible desde la ficha del neumático (web y app) cuando la cubierta está en `EN_REPARACION`. Cierra la vida actual, abre una nueva, pero **no** pasa por ningún `WorkOrder` — no hay taller, no hay costo, no hay número de OT. Se registra como `tire_movements` tipo `FromRepair`.

**El escenario "enviar X unidades de un stock/medida a recapar sin seleccionar manualmente cada cubierta" NO está soportado hoy.** Todo el código exige `Tire` (instancias reales) o `tire_id` explícito, en ambas vías.

La identificación individual (`individual_number`, DOT, `public_token`) se conserva en las dos vías — no hay pérdida de trazabilidad a nivel de cubierta, el problema es la falta de un único punto de verdad para "esto fue un recapado".

### 0.7 Inventario Web + App

| Funcionalidad | Web | App | Misma regla de negocio |
|---|---|---|---|
| Consulta | SI (`resources/views/inventories/index.blade.php`) | SI (`mobile/app/(tabs)/inventory-sessions/index.tsx`) | SI — ambas llaman a `InventorySession` vía Eloquent/API, mismo `AccessScope`. |
| Movimiento | N/A (el inventario físico no genera movimientos de stock directamente, ver 0.9) | N/A | — |
| Recapado | No aplica a inventario | No aplica a inventario | — |
| Inventario (abrir/escanear/revisar/cerrar/cancelar) | SI (`app/Http/Controllers/InventoryController.php`) | SI, agregado **en esta misma sesión** (`ApiSurfaceController::storeInventorySession`/`showInventorySession`/`startInventorySession`/`scanInventorySession`/`reviewInventorySession`/`closeInventorySession`/`cancelInventorySession`) | SI — la app llama exactamente a `InventoryService`, mismas Policies (`InventorySessionPolicy`). Verificado por lectura de código; **no verificado en ejecución real** (ver 0.1, fila App). |
| Ajustes | SI (`apply_fixes` en `close()`) | SI (mismo parámetro, mismo endpoint) | SI. |
| OT | SI (`app/Http/Controllers/WorkOrderController.php`) | SI, agregado **en esta misma sesión** (`ApiSurfaceController::showWorkOrder`/`sendWorkOrderToShop`/`closeWorkOrder`/`cancelWorkOrder`) | SI — mismo `WorkOrderService`, misma `WorkOrderPolicy`. Mismo caveat: no probado en ejecución real todavía. |

No se encontró lógica de negocio de OT o Inventario implementada en JavaScript (`resources/views/work-orders/*.blade.php`, `resources/views/inventories/*.blade.php` no usan `fetch`/`axios` — son formularios POST comunes). Toda la regla vive en `app/Services`.

### 0.8 Riesgos principales

| Riesgo | Severidad | Causa | Consecuencia |
|---|---|---|---|
| Doble criterio de "qué es un recapado" | CRÍTICO | Dos code paths independientes (0.2) | Reportes/consultas que solo miren `tire_movements` van a subcontar recapados hechos por OT; los que solo miren `tire_incidents` van a subcontar los hechos por retorno directo. |
| Cambios futuros a `WorkOrder` asumiendo que cubre vehículos | ALTO | No hay `unit_id` en `work_orders`, pero el nombre "Orden de Trabajo" invita a esa suposición | Migración mal diseñada si se agrega sin revisar `TireOperation` primero. |
| Falta de test en cierre de OT Recapado | CRÍTICO | Cobertura de tests despareja (0.2) | Un cambio futuro en `WorkOrderService::close()` puede romper el recapado por OT sin que ningún test lo detecte. |
| `AuditLog` editable | MEDIO | Sin Observer de inmutabilidad | Un bug o un script mal escrito podría alterar el rastro de auditoría sin que nada lo impida. |
| Medición móvil rota | MEDIO | Formulario de un solo campo vs regla de "todas las zonas" | Pérdida de datos de desgaste desde el teléfono (no bloquea OT/Stock/Inventario directamente, pero sí la trazabilidad de condición física). |

### 0.9 Cambios recomendados

**Obligatorios** (para que el modelo represente correctamente lo que el negocio pide):
- Decidir y documentar una única fuente de verdad para "esta cubierta fue recapada" (DEC-001).
- Agregar test de `WorkOrderService::close()` con `type=RECAPADO` (hoy no existe ninguno).

**Recomendados:**
- Enlazar `WorkOrder` con los `tire_movements` que genera (aunque sea con una columna `tire_operation_id`-like o un `reason_id`/referencia), para que un reporte de historial pueda mostrar "este movimiento vino de la OT-123".
- Proteger `AuditLog` con el mismo patrón que `TireMovement` (Observer de inmutabilidad).
- Mover el chequeo `canRetireOrRecap()` de `TireOperationService::returnToStock()` también a `TirePolicy` (o a una policy específica), para que quede en un solo lugar visible.

**Opcionales:**
- Evaluar si vale la pena introducir un concepto de "lote/cantidad" para altas y envíos a recapado masivos (DEC-002) — es un cambio de modelo, no una corrección de bug.
- Limpiar `MovementType::FromSpare` (no usado) o implementarlo donde corresponde.

### 0.10 Decisiones pendientes

| ID | Decisión | Motivo | Impacto |
|---|---|---|---|
| DEC-001 | Unificar los dos caminos de recapado, o documentar explícitamente que son dos flujos distintos a propósito (uno "con taller formal", otro "corrección rápida de ficha"). | El código no permite determinar cuál de los dos es "el oficial" — ambos están completos y probados (uno más que el otro). | Alto: afecta reportes de recapado, costos y auditoría. |
| DEC-002 | Si el negocio realmente necesita operar "por cantidad de stock" (sección 3 del pedido) en vez de por cubierta individual, eso es un cambio de modelo de dominio, no una corrección — hoy **todo** el sistema (compras, OT, operaciones de unidad) trabaja sobre `Tire` individuales. | El pedido lo pide explícitamente pero el repositorio no tiene ningún precedente de "cantidad sin identidad" — introducirlo tocaría `PurchaseService`, `WorkOrderService` y probablemente `TireOperationService`. | Crítico si se decide que sí; ninguno si se decide que no (el modelo actual ya resuelve compras por cantidad *que después se individualizan*, ver `PurchaseService::confirm()`). |
| DEC-003 | Si conviene vincular formalmente `WorkOrder` a `fleet_units`, para que una OT de "cambio de neumático" en ruta también quede como Orden de Trabajo (hoy es una `TireOperation`, sin ese nombre). | El pedido describe una OT que cubre montaje/desmontaje/rotación; hoy eso es una entidad distinta. Unificarlas es una decisión de arquitectura, no un bug. | Alto — tocaría el corazón de `TireOperationService`, que ya está fuertemente probado. |

### 0.11 Estado de preparación para implementación

**`NO LISTO PARA IMPLEMENTAR`** *(al momento de este informe original — ver adenda de más arriba para lo que se implementó después)*

Justificación original: existen dos decisiones críticas sin resolver (DEC-001 sobre cuál camino de recapado es la fuente de verdad, y DEC-003 sobre si "OT" debe unificar `WorkOrder` + `TireOperation`) que cambian qué archivos hay que tocar y cómo. Implementar sin resolverlas arriesga profundizar la duplicación en vez de resolverla. El resto del sistema (Stock, Movimientos, Inventario físico) sí está listo — la traba es específicamente el concepto de OT/Recapado.

**Actualización (2026-09-22):** se implementaron todas las correcciones que no dependían de DEC-001/002/003 (ver adenda al inicio del documento). DEC-002 y DEC-003 siguen sin resolver porque son cambios de modelo de negocio, no bugs — implementarlos sin que el negocio los confirme contradice la instrucción de no eliminar/cambiar funcionalidad sin justificarlo. Con las correcciones aplicadas, el estado pasa a **`LISTO CON CONDICIONES`**: listo para producción en lo corregido, condicionado a correr la suite de tests en un entorno con base de datos real antes de desplegar (no se pudo ejecutar acá) y a que el negocio confirme o descarte DEC-002/DEC-003 antes de pedir trabajo adicional sobre esos puntos.

---

## 1. MAPA DEL REPOSITORIO

| Área | Ubicación | Responsabilidad | Observaciones |
|---|---|---|---|
| Backend | `app/` (Laravel 13, PHP 8.3) | Todo el dominio: modelos, servicios, controladores, policies | Arquitectura de "controlador delgado, servicio grueso" — confirmado en los 5 dominios auditados. |
| Web | `resources/views/*.blade.php` + `resources/js/app.js` | Blade + Tailwind, formularios server-rendered | Sin SPA. JS solo para la planilla de unidad (mapa de posiciones), no para OT/Inventario. |
| App | `mobile/` (Expo Router + TypeScript) | Cliente API para operación de campo | Consume `routes/api.php` vía `mobile/src/api/client.ts`. OT/Inventario agregados en esta sesión. |
| Base de datos | MySQL 8 (Railway) / SQLite en tests | — | `docker-compose.yml`, `phpunit.mysql.xml`. |
| Migraciones | `database/migrations/*.php` | Esquema | Núcleo en `2026_08_13_100000_create_traceability_tables.php` (436 líneas); OT/Recapadoras/Costos en `2026_08_19_200000_production_hardening.php`. |
| API | `routes/api.php` | JSON, Sanctum | Prefijo `/api/v1`, todas las rutas de escritura con middleware `capability:*`. |
| Servicios | `app/Services/*.php` (≈25 archivos) | Reglas de negocio | `WorkOrderService`, `TireOperationService`, `InventoryService`, `PurchaseService`, `RetirementService`, `IncidentService`, `MeasurementService`, `LocationService`, `AuditService`, `IntegrityService`, `BaseTransferService`, `CouplingService`, `CostService`, `TireIdentityService`, `OdometerService`, `MovementCorrectionService`, `OpeningStockService`, `ConfigurationChangeService`, `PredictiveWearService`, `RotationPatternService`, `TirePhotoService`, `TelemetryService`, `DocumentNumberService`, `PositionFitService`. |
| Modelos | `app/Models/*.php` (≈30 archivos) | Entidades | Ver sección 3. |
| Tests | `tests/Feature/*.php`, `tests/Unit/` (vacía) | Cobertura | 100% Feature tests; `tests/Unit` sin archivos. |

---

## 2. MODELO ACTUAL

| Entidad | Archivo/Modelo | Propósito | Relaciones | Problemas |
|---|---|---|---|---|
| Neumático | `app/Models/Tire.php` | Activo individual | `brand`, `model`, `size`, `currentLocation` (1:1), `movements`, `incidents`, `measurements`, `lifecycles`, `currentLifecycle`, `openAssignment`, `purchaseItem` | Ninguno estructural. |
| Stock | *(no existe como entidad)* | — | — | No es una tabla; es `Tire::where('status','STOCK')`. Correcto para el dominio, pero hay que tenerlo claro antes de "agregar un ajuste de stock" (no hay cantidad que ajustar, hay cubiertas que mover). |
| Movimiento | `app/Models/TireMovement.php` | Historia inmutable por cubierta | `tire`, `operation` (`tire_operation_id`), `fromUnit`/`toUnit`/`fromPosition`/`toPosition`/`fromBase`/`toBase`, `reason` | Ninguno — es el modelo más sólido del repo. |
| Orden de Trabajo | `app/Models/WorkOrder.php` + `app/Models/WorkOrderItem.php` | Cubierta(s) enviada(s) a taller externo (Recapado/Reparación) | `tire` (principal), `items.tire` (1..N), `shop` (`RetreadShop`), `opener`/`closer` (`User`) | **Sin relación a `fleet_units`.** No representa montaje/desmontaje/rotación. |
| Operación de unidad | `app/Models/TireOperation.php` | Agrupa los movimientos de **una** planilla (montar/rotar/retirar en un mismo envío) | `unit`, `odometerUnit`, `user`, `movements` (1:N) | Es, funcionalmente, la "OT de campo" — pero nunca se la llama así en código ni UI. |
| Vehículo | `app/Models/FleetUnit.php` | Camión/semi/tanque/batea | `type`, `configuration`, `fleet`, `base`, `locations`, `couplings` | Fuera del foco de esta auditoría salvo por su ausencia de relación con `WorkOrder`. |
| Inventario (cantidad) | *(no existe)* | — | — | No hay concepto de "cantidad esperada de un SKU" independiente de las cubiertas físicas. |
| Inventario físico | `app/Models/InventorySession.php` + `app/Models/InventoryLine.php` | Conteo de una base: teórico vs contado | `base`, `opener`/`closer`/`approver`, `lines.tire` | Bien resuelto (ver 7-8). |
| Lote | *(no existe como concepto propio)* | — | `tire_purchase_items.quantity` es lo más cercano (una compra puede ser de N unidades) | Un lote de compra **se individualiza inmediatamente** al confirmar (`PurchaseService::confirm()` crea N filas `tires`). No hay "lote" después de ese punto. |
| Ubicación | `app/Models/TireCurrentLocation.php` | Dónde está la cubierta AHORA (1 fila por cubierta) | `tire` (unique), `base`, `unit`, `position` | Ninguno. |
| Recapado | *(no es una entidad — es un `WorkOrderType`, un `IncidentType` y un flag `asRecap`)* | — | — | Ver hallazgo CRÍTICO 0.2. |
| Reparación | Igual que Recapado: `WorkOrderType::Reparacion`, `IncidentType::Reparacion`/`Parche`, `TireCondition::Reparada` | — | — | Más consistente que Recapado: solo dos caminos (OT o incidencia directa de campo), y ambos llevan a la misma condición sin duplicar "vida nueva". |

---

## 3. MODELO CONCEPTUAL

```text
NEUMÁTICO (Tire)
    │  activo individual — identidad: individual_number, DOT, public_token/QR
    ↓
UBICACIÓN ACTUAL (TireCurrentLocation)
    │  1 fila por neumático — dónde está AHORA (stock/instalada/auxilio/reparación/baja)
    ↓
MOVIMIENTO (TireMovement)
    │  historia inmutable — 1 fila por transición, nunca se edita/borra
    ↓
   ┌─────────────────────────────┬───────────────────────────────┐
   │                              │                                │
OPERACIÓN DE UNIDAD          ORDEN DE TRABAJO (WorkOrder)     INVENTARIO FÍSICO
(TireOperation)               │  taller externo:                (InventorySession)
 │  montar/rotar/retirar        │  Recapado o Reparación          │  conteo por base:
 │  en un vehículo               │  (NO toca vehículos)            │  teórico vs contado
 ↓                              ↓                                 ↓
genera N TireMovement      cierra → IncidentService            genera diferencias
                            (Recapado) o                        (InventoryLine.delta),
                            TireOperationService::returnToStock  ajuste opcional
                            (Reparación) — AMBOS
                            terminan en TireMovement/TireIncident
```

**Responsabilidad de cada concepto (tal como está hoy, no como debería estar):**

- **Neumático**: identidad y atributos físicos (marca/modelo/medida/condición). No sabe dónde está ni qué le pasó — eso lo dicen `TireCurrentLocation` y `TireMovement`.
- **Stock**: no es un concepto con estado propio. Es una lectura derivada (`status = STOCK`) sobre Neumático.
- **Movimiento**: el único lugar donde "algo pasó" queda escrito para siempre.
- **Orden de Trabajo**: hoy, exclusivamente "cubierta(s) + taller externo". No es la operación de negocio genérica que describe el punto 1 del pedido.
- **Operación de unidad** (`TireOperation`): la operación de negocio genérica sobre un vehículo (montar/desmontar/rotar), sin ese nombre.
- **Inventario / Inventario físico**: solo existe la segunda. No hay "inventario" como sinónimo de "listado de stock" separado de la Orden de Trabajo o del stock en sí.
- **Lote**: existe solo durante la compra, antes de confirmarse. No es una entidad persistente después.
- **Ubicación**: base, unidad o posición — siempre 1:1 con el neumático.
- **Vehículo**: dueño de posiciones montables; relacionado con Operación de unidad, no con Orden de Trabajo.
- **Recapado**: un resultado de negocio (condición + vida nueva), alcanzable por dos caminos de código distintos.

---

## 4. ORDEN DE TRABAJO

### Estados actuales

| Estado | Existe | Uso actual | Problemas |
|---|---|---|---|
| `ABIERTA` | SI | Creada, cubierta(s) aún no físicamente en el taller | Ninguno |
| `EN_TALLER` | SI | `sendToShop()` la mueve acá; cubiertas Stock pasan a `EN_REPARACION` | Ninguno |
| `CERRADA` | SI | Terminal — `close()`; no admite más transiciones | Ninguno |
| `CANCELADA` | SI | Terminal — `cancel()`; rechaza si ya estaba `CERRADA` | Ninguno |
| `BORRADOR` (pedido por el usuario) | **NO EXISTE** | — | No hay estado previo a `ABIERTA`; `open()` crea la OT directamente en `ABIERTA`. Ver DEC pendiente si se quiere agregar. |
| `EN_PROCESO` (pedido por el usuario) | **NO EXISTE como tal** | El equivalente real es `EN_TALLER` | Es solo una diferencia de nombre, no de concepto — no recomiendo agregar un estado nuevo, `EN_TALLER` ya cumple ese rol (ver sección 13, RN-002). |

### Estados propuestos

No se proponen estados nuevos. Los 4 existentes (`ABIERTA`, `EN_TALLER`, `CERRADA`, `CANCELADA`) cubren el ciclo de vida real encontrado en el código y en los tests. Agregar `BORRADOR` sin una razón de negocio concreta (¿quién la crearía en borrador y por qué no directamente abierta?) sería un estado sin transición de entrada real — contradice la instrucción de "no agregar estados arbitrariamente".

| Estado | Descripción | Entrada permitida desde | Salidas permitidas |
|---|---|---|---|
| `ABIERTA` | Creada, cubiertas aún no en taller | (creación) | `EN_TALLER` (enviar), `CANCELADA` |
| `EN_TALLER` | Cubiertas físicamente en el taller | `ABIERTA` | `CERRADA` (cerrar), `CANCELADA` |
| `CERRADA` | Trabajo terminado, costo asentado | `EN_TALLER` (o `ABIERTA` directamente — `close()` no exige pasar por `EN_TALLER`, ver RN-004) | (ninguna — terminal) |
| `CANCELADA` | Anulada | `ABIERTA`, `EN_TALLER` | (ninguna — terminal) |

### Operaciones soportadas

| Operación | Actualmente existe | Debería existir | Observaciones |
|---|---|---|---|
| Creación | SI (`WorkOrderService::open`) | SI | Con Gate `create` = `canWrite()`; Recapado además exige `canRetireOrRecap()` (chequeo inline, no en Policy). |
| Envío a taller | SI (`sendToShop`) | SI | Mueve cubiertas `STOCK` → `EN_REPARACION`. |
| Cierre | SI (`close`) | SI | Costo opcional, prorrateado entre ítems (`CostService::attributionFromTire` + `splitCost`). |
| Cancelación | SI (`cancel`) | SI | Rechaza si ya `CERRADA`. |
| Modificación (editar ítems/tipo/taller) | **NO EXISTE** | NO DETERMINADO | No hay ningún `update()` sobre `WorkOrder`. Si el negocio necesita corregir una OT abierta (cambiar de taller, agregar una cubierta), hoy no se puede — habría que cancelar y volver a abrir. |
| Relación con vehículo | NO EXISTE | Ver DEC-003 | — |
| Relación con neumáticos | SI | SI | `tire_id` (principal, obligatorio) + `work_order_items` (soporta múltiples, pero solo para `RECAPADO` — `Reparacion` rechaza más de 1). |
| Relación con movimientos | PARCIAL | Recomendado | Indirecta vía `ToRepair`/`FromRepair`, sin FK explícita OT↔movimiento. |
| Auditoría | SI | SI | `audit_logs`: `work_order.opened`, `work_order.sent`, `work_order.closed`, `work_order.cancelled`. |

---

## 5. RECAPADO

### Flujo actual

```text
VÍA A — Orden de Trabajo
STOCK
  ↓ WorkOrderService::open(tires, shop, RECAPADO)
ABIERTA (tire(s) siguen en STOCK)
  ↓ sendToShop()
EN_TALLER (tire(s) → EN_REPARACION)
  ↓ close(cost, notes)
      → por cada tire: IncidentService::register(RECAPADO)
          → cierra vida actual, abre vida N+1, condition = RECAPADA
          → NO cambia status/ubicación (el tire sigue en EN_REPARACION
            hasta que alguien lo devuelva a stock aparte — ver RN-006)
CERRADA

VÍA B — retorno directo (sin Orden de Trabajo)
[cualquier camino que haya dejado al tire en EN_REPARACION]
  ↓ TireOperationService::returnToStock(tire, user, notes, asRecap: true)
      → requiere canRetireOrRecap()
      → cierra vida actual, abre vida N+1, condition = RECAPADA
      → status → STOCK (sí mueve la ubicación, a diferencia de la Vía A)
      → tire_movements tipo FromRepair
STOCK
```

**Diferencia funcional importante ya detectada:** en la Vía A (`close()` de una OT Recapado), el `IncidentService::register()` deja la cubierta marcada `RECAPADA` pero **no** la devuelve a `STOCK` — el `elseif` de `close()` que sí llama a `returnToStock()` está condicionado a `$order->type !== WorkOrderType::Recapado` (rama exclusiva de `Reparacion`, ver `app/Services/WorkOrderService.php:130-141`). Confirmar contra el código si esto es intencional (¿una recapada cerrada queda pendiente de otro paso manual para pasar a Stock?) o es un hueco — se listó como **DEC-004** más abajo porque no hay test que lo aclare.

### Flujo propuesto

No corresponde proponer un flujo nuevo sin resolver primero **DEC-001** (cuál vía es la oficial) y **DEC-004** (si Recapado por OT debe volver solo a `STOCK` igual que Reparación). El flujo ideal del pedido:

```text
STOCK
  ↓
SELECCIÓN DE CANTIDAD / LOTE   ← NO SOPORTADO HOY (ver DEC-002)
  ↓
ENVÍO A RECAPADO
  ↓
EN RECAPADO
  ↓
RECEPCIÓN
  ↓
INSPECCIÓN
  ↓
STOCK
```

ya existe **por cubierta individual** en la Vía A, salvo por el paso "STOCK" final (ver arriba) y por no soportar selección por cantidad. El camino de rechazo:

```text
EN RECAPADO
  ↓
RECHAZADO
  ↓
BAJA / OTRO DESTINO
```

**NO EXISTE.** No hay ningún estado ni transición para "el taller rechazó la cubierta". Hoy, si una cubierta enviada a recapar no vuelve en condiciones, la única salida es: (a) cerrar la OT igual y luego dar de baja la cubierta por separado (`RetirementService::retire`, que exige que no esté montada — sí lo permite si está en `EN_REPARACION`/`STOCK`), o (b) cancelar la OT (`cancel()`), que solo revierte el flag `open_tire_id` del ítem, sin registrar "fue rechazada". No hay forma de distinguir en el historial "cancelé la OT porque me arrepentí" de "el taller la rechazó".

El sistema trabaja **por unidad individual** en ambas vías, nunca por cantidad ni por lote. La identificación individual se conserva siempre.

---

## 6. INVENTARIO

| Concepto del pedido | Clasificación | Evidencia |
|---|---|---|
| Stock teórico | Dato calculado, no campo | `InventoryService::open()` arma el snapshot contando `Tire::whereIn('status', STOCKABLE)` en el momento de abrir (`expected_count`). |
| Stock físico (contado) | Dato calculado | `found_count`, resultado de los escaneos (`InventoryLine.found`). |
| Stock disponible | Estado (`TireStatus::Stock`) | No es un campo separado. |
| Stock reservado | Estado (`TireStatus::Reserva`) | Idem. |
| Stock en tránsito | **NO EXISTE** | No hay un estado "en tránsito" entre bases; `BaseTransferService::transfer()` mueve directo de una base a otra sin estado intermedio. |
| Stock en recapado | Estado (`TireStatus::EnReparacion`, compartido con reparación — ver 0.2) | No distingue "en el taller por recapado" de "en el taller por reparación" a nivel de `status`; la distinción vive en la `WorkOrder`/`TireIncident` asociada, no en el estado del neumático. |
| Stock en reparación | Igual que el anterior | Mismo `status`, mismo comentario. |
| Stock montado | Estado (`TireStatus::Instalada` / `Auxilio`) | Correcto como estado. |
| Stock dado de baja | Estado (`TireStatus::DeBaja`) | Terminal, irreversible (`RetirementService`). |

**Conclusión de esta sección:** el modelo YA separa correctamente "condición" (`TireCondition`: Nueva/Nueva usada/Usada/Recapada/Reparada) de "situación operativa/ubicación" (`TireStatus`/`LocationKind`: Stock/Reserva/Instalada/Auxilio/EnReparacion/DeBaja). El único solapamiento real es que `EN_REPARACION` no distingue "por qué está ahí" (recapado vs reparación) — esa información vive en una entidad relacionada (`WorkOrder`/`TireIncident`), no en el propio estado, lo cual es correcto en general pero se vuelve confuso por la duplicación de caminos (0.2).

---

## 7. INVENTARIO FÍSICO

```text
INVENTARIO ABIERTO (OPEN)
       ↓ InventoryService::open() — snapshot de tires STOCKABLE en esa base
CONTEO (COUNTING)
       ↓ InventoryService::startCounting() + scan() por cada cubierta
COMPARACIÓN
       ↓ InventoryService::submitForReview() — marca MISSING lo no escaneado
DIFERENCIA (REVIEW)
       ↓ delta: OK / MISSING / UNEXPECTED / WRONG_BASE / MOUNTED
AJUSTE (opcional, apply_fixes)
       ↓ InventoryService::close(applyLocationFixes: true) — solo mueve WRONG_BASE/UNEXPECTED
         vía BaseTransferService::transfer(); NUNCA desmonta ni da de baja
CIERRE (CLOSED)
```

**Información conservada** (confirmada en `inventory_sessions` + `inventory_lines`): stock teórico (`expected_count`, y por línea `expected_kind`/`expected_base_id`), cantidad contada (`found_count`, `InventoryLine.found`), diferencia (`InventoryLine.delta`, y a nivel sesión `missing_count`/`unexpected_count`), usuario (`opened_by`/`closed_by`/`scanned_by`), fecha (`opened_at`/`counting_started_at`/`submitted_at`/`closed_at`), depósito (`base_id`), motivo/observaciones (`notes`, a nivel sesión y línea), movimientos generados (`adjustment_applied` por línea; el movimiento real lo genera `BaseTransferService`, que sí crea un `TireMovement` tipo `TransferBase`).

**Cómo se evita modificar stock sin trazabilidad:** `close()` con `applyLocationFixes=true` nunca actualiza `tires`/`tire_current_locations` directamente — delega en `BaseTransferService::transfer()`, que (no leído línea por línea en esta auditoría, pero confirmado por su uso consistente en el resto del sistema) sigue el mismo patrón de `LocationService::place()` + `TireMovement`.

**Regla "una sesión activa por base":** confirmada en código (`InventoryService::open()`, `app/Services/InventoryService.php:44-52`) y en test (`tests/Feature/PhysicalInventoryTest.php:137`, `test_only_one_active_session_per_base`).

---

## 8. MATRIZ DE OPERACIONES

| Operación | Cantidad | Lote | Unidad | Movimiento | OT | Inventario | Observaciones |
|---|---:|---:|---:|---:|---:|---:|---|
| Alta de stock (compra) | SI (al crear el borrador) | SI (hasta confirmar) | SI (se individualiza al confirmar) | SI (`PURCHASE_IN`) | NO | NO | `PurchaseService`: `create()` es por cantidad; `confirm()` la convierte en N `Tire` individuales de una. |
| Cambio de neumático (en unidad) | NO | NO | SI | SI (`INSTALL`/`REMOVE_TO_STOCK` en una misma operación) | NO | NO | Vía `TireOperationService::execute()`, no vía `WorkOrder`. |
| Montaje | NO | NO | SI | SI (`INSTALL`) | NO | NO | Idem. |
| Desmontaje | NO | NO | SI | SI (`REMOVE_TO_STOCK`) | NO | NO | Idem. |
| Reparación | NO | NO | SI | SI (`TO_REPAIR` / `FROM_REPAIR`) | SI (opcional — puede iniciarse por incidente de campo sin OT) | NO | Dos entradas posibles: `WorkOrder` o incidente directo (`pinchadura`/`parche`). |
| Envío a recapado | NO | NO | SI | PARCIAL (ver 0.2) | SI (vía A) / NO (vía B) | NO | Ver sección 5. |
| Recepción de recapado | NO | NO | SI | SI (solo vía B) | PARCIAL (vía A no genera movimiento, ver 5) | NO | — |
| Baja | NO | SI (`retireMany`, procesa una por una) | SI | SI (`RETIRE`) | NO | NO | `RetirementService::retireMany()` acepta un iterable, pero sigue procesando de a una cubierta con su propia validación — no es un "lote atómico". |
| Inventario físico | NO (cuenta cubiertas, no cantidades) | NO | SI | SI, solo si hay ajuste (`TRANSFER_BASE`) | NO | SI | — |
| Ajuste de inventario | NO | NO | SI (por línea) | SI (`TRANSFER_BASE`, solo para `WRONG_BASE`/`UNEXPECTED` stockable) | NO | SI | Nunca ajusta `MISSING` ni `MOUNTED` — esas quedan solo auditadas. |
| Venta | **NO IMPLEMENTADO** | — | — | — | — | — | No se encontró ningún concepto de "venta" de neumáticos en el repositorio (ni modelo, ni movimiento, ni ruta). Marcado `NO DETERMINADO` si el negocio lo necesita — no hay evidencia de que exista ni de que se haya descartado a propósito. |

---

## 9. MOVIMIENTOS DE STOCK

| Archivo | Función | Movimiento generado | Auditoría | Riesgo |
|---|---|---|---|---|
| `app/Services/PurchaseService.php` | `confirm()` | `PURCHASE_IN` | `purchase.created` (en `create()`; `confirm()` no llama `AuditService` directamente — **hueco menor**, ver más abajo) | Bajo — dentro de `DB::transaction`, con `lockForUpdate` implícito vía `DocumentNumberService`. |
| `app/Services/TireOperationService.php` | `execute()` (montar/retirar/rotar) | `INSTALL` / `REMOVE_TO_STOCK` / `ROTATE` / `TO_RESERVA` / `TO_REPAIR` | `tire.operation` | Bajo — `lockForUpdate()` explícito sobre tires y ubicaciones antes de escribir (líneas 71-73). |
| `app/Services/TireOperationService.php` | `returnToStock()` | `FROM_REPAIR` / `FROM_RESERVA` | `tire.returned_stock` | Bajo — `lockForUpdate()` sobre el tire (línea 464). |
| `app/Services/RetirementService.php` | `retire()` | `RETIRE` | `tire.retired` | Bajo. |
| `app/Services/WorkOrderService.php` | `sendToShop()` | `TO_REPAIR` (solo si el tire estaba en `STOCK`) | `work_order.sent` | Bajo/Medio — si el tire ya no está en `STOCK` (p. ej. lo movieron por fuera) no genera movimiento y no avisa; queda en silencio. |
| `app/Services/WorkOrderService.php` | `close()` (Recapado) | **Ninguno** — genera `TireIncident`, no `TireMovement` | `work_order.closed` | Ver hallazgo CRÍTICO 0.2. |
| `app/Services/BaseTransferService.php` | `transfer()` | `TRANSFER_BASE` | (no confirmado en esta pasada — recomendado revisar) | — |
| `app/Services/ConfigurationChangeService.php` | (cambio de configuración de unidad) | `REMOVE_TO_STOCK` | (no confirmado en esta pasada) | — |
| `app/Services/MovementCorrectionService.php` | `correct()` (no leído en detalle) | `CORRECTION` | (no confirmado) | Existe explícitamente para corregir sin reescribir — coherente con la regla de inmutabilidad. |
| `app/Services/OpeningStockService.php` | (carga inicial de stock, "punto de partida") | `OPENING_IN` | (no confirmado) | — |

**Modificaciones directas de cantidades (`quantity += X` / `quantity -= X`):** no se encontró ningún caso. No existe un campo de cantidad de stock que se edite directamente — todo pasa por `LocationService::place()` (que actualiza `tires.status` + `tire_current_locations`, siempre dentro de una transacción con lock) seguido de la creación de un `TireMovement`. **Esto responde afirmativamente a la pregunta obligatoria de la sección 25 del pedido: no hay operación que salte el flujo OPERACIÓN → VALIDACIÓN → TRANSACCIÓN → MOVIMIENTO → ACTUALIZACIÓN DE STOCK, con la única salvedad ya señalada del cierre de OT Recapado, que actualiza condición pero no ubicación/movimiento.**

---

## 10. TRAZABILIDAD

**¿Puedo reconstruir la historia completa de un neumático?**

| Punto | Estado | Evidencia |
|---|---|---|
| Alta | COMPLETO | `tire_movements` tipo `PURCHASE_IN`/`OPENING_IN` + `tire_lifecycles` vida 1. |
| Ingreso/ubicación | COMPLETO | `tire_current_locations` (actual) + `tire_movements` (historia). |
| Movimientos | COMPLETO | `tire_movements`, inmutable. |
| Montaje/desmontaje | COMPLETO | `tire_assignments` + `tire_assignment_segments` (km por tramo) + `tire_movements`. |
| Reparación | COMPLETO | `tire_incidents` + `tire_movements` (`TO_REPAIR`/`FROM_REPAIR`) + `tire_lifecycles.condition_at_start`. |
| Recapado | **PARCIAL** | Completo si fue vía B (`tire_movements` tipo `FROM_REPAIR` + nueva `tire_lifecycles`). Incompleto si fue vía A: solo `tire_incidents` (tipo `RECAPADO`) + nueva vida; sin `tire_movements` asociado, y sin quedar la cubierta de vuelta en `STOCK` automáticamente (ver DEC-004). |
| Recepción (de recapado) | PARCIAL | Mismo comentario que arriba. |
| Inventario | COMPLETO | `inventory_lines` conserva teórico/contado/diferencia por sesión. |
| Baja | COMPLETO | `tire_movements` tipo `RETIRE` + `retired_at` + fotos (`TirePhotoService`). |
| ¿Cuántas veces fue recapado? | PARCIAL | Se puede contar `tire_lifecycles.started_by = 'RECAPADO'` (vía B) pero **no** hay un `started_by` equivalente para la vía A (usa `IncidentService`, no confirmado si setea el mismo campo — **NO DETERMINADO**, requiere lectura adicional de `IncidentService::register()` línea por línea, que esta auditoría no completó por límite de tiempo). |
| ¿En qué OT ocurrió? | **NO EXISTE** un campo directo en `tire_movements` que apunte a `work_order_id` (sí existe `tire_operation_id`, que es la entidad de planilla, no la OT). Se puede inferir indirectamente cruzando fechas y `tire_id`, pero no hay FK. |

---

## 11. WEB VS APP

| Funcionalidad | Web | App | Lógica compartida | Observaciones |
|---|---:|---:|---:|---|
| Consulta de stock | SI | SI | SI | Ambas listan `Tire` filtrando por `status`/`condition`/búsqueda parcial (agregada en esta sesión). |
| Alta | SI (`PurchaseController`, por lote) | SI (`ApiSurfaceController::storeTire`, de a 1, reusa `PurchaseService`) | SI | La app simplifica a "1 cubierta por vez" pero llama al mismo Service — no reimplementa la regla de negocio. |
| Movimiento (montar/retirar/rotar) | SI (`UnitController::slotAction`) | SI (`TireApiController::operate`, mapa visual de unidad) | SI | Ambas llaman `TireOperationService::execute()`. |
| OT | SI (`WorkOrderController`) | SI (agregado hoy, `ApiSurfaceController`) | SI | Mismo `WorkOrderService`/`WorkOrderPolicy`. Sin prueba de uso real todavía. |
| Recapado | SI (ambas vías) | Solo vía B (`api.returnToStock` con `as_recap`); la app no tiene una pantalla específica para abrir una OT de Recapado con selección múltiple de cubiertas — puede abrir una OT de a una cubierta desde la ficha. | SI (mismos Services) | La app no reproduce el caso "recapado en lote" que sí existe en web (`test_work_order_recap_can_group_multiple_tires`) — no es una regla de negocio distinta, es una funcionalidad de UI que la app no expone todavía. |
| Inventario | SI | SI (agregado hoy) | SI | Mismo `InventoryService`/`InventorySessionPolicy`. |
| Ajustes | SI | SI (agregado hoy, `apply_fixes`) | SI | — |

**No se encontró ninguna diferencia de regla de negocio entre Web y App en los 5 dominios auditados.** Toda la lógica está centralizada en `app/Services`; las plataformas difieren solo en interfaz. La única brecha real es de **cobertura de funcionalidad** (la app no ofrece selección múltiple para OT de recapado) y de **madurez** (lo agregado en la app hoy no tiene aún ni un test ni un uso real confirmado).

---

## 12. REGLAS DE NEGOCIO

### RN-001 — Toda modificación de ubicación/stock pasa por un movimiento inmutable

**Regla:** Ningún cambio de `tires.status` o `tire_current_locations` puede ocurrir sin crear una fila en `tire_movements`, y esa fila no puede editarse ni borrarse después.

**Condición:** Cualquier operación que mueva una cubierta (compra, montaje, desmontaje, rotación, retiro, transferencia de base, baja).

**Resultado:** Se crea `tire_movements` con tipo, usuario, fecha y, según el tipo, origen/destino. Un `UPDATE`/`DELETE` sobre `tire_movements` es rechazado por trigger de MySQL y por `ImmutableRecordObserver`.

**Excepción:** El cierre de una OT de tipo Recapado (vía A) actualiza la condición del neumático sin crear un `tire_movements` (crea un `TireIncident` en su lugar). Ver DEC-001.

**Estado:** CONFIRMADO (con la excepción documentada). Evidencia: `database/migrations/2026_08_21_140000_protect_tire_movements_with_triggers.php`, `app/Observers/ImmutableRecordObserver.php`, `tests/Feature/ProductionHardeningTest.php:53` (`test_tire_movements_are_immutable`).

### RN-002 — Una Orden de Trabajo cerrada o cancelada es terminal

**Regla:** No se puede volver a operar sobre una `WorkOrder` en estado `CERRADA` o `CANCELADA`.

**Condición:** Cualquier intento de `sendToShop`/`close`/`cancel` sobre una OT ya terminal.

**Resultado:** `DomainException` (`'La orden ya está cerrada o cancelada.'`, `'Una OT cerrada no se cancela...'`).

**Excepción:** Ninguna encontrada.

**Estado:** CONFIRMADO. Evidencia: `app/Services/WorkOrderService.php:96-98` (`close`), `:191-194` (`cancel`).

### RN-003 — Una Orden de Trabajo de tipo Reparación admite una sola cubierta

**Regla:** `WorkOrderType::Reparacion` no puede abrirse con más de una cubierta.

**Condición:** `open()` con `type = REPARACION` y más de un `tire_id`.

**Resultado:** `DomainException`.

**Excepción:** `WorkOrderType::Recapado` sí admite varias (lote de cubiertas seleccionadas individualmente, no por cantidad).

**Estado:** CONFIRMADO. Evidencia: `app/Services/WorkOrderService.php:44-46`; test `tests/Feature/ProductionHardeningTest.php:236` (`test_work_order_repair_rejects_multiple_tires`).

### RN-004 — Una cubierta no puede estar en dos Órdenes de Trabajo abiertas a la vez

**Regla:** No se puede abrir una segunda OT para una cubierta que ya tiene una abierta.

**Condición:** `open()` con una cubierta cuyo `work_order_items.open_tire_id` (o `work_orders` con status abierto) ya existe.

**Resultado:** `DomainException`; reforzado por constraint `unique` en `work_order_items.open_tire_id` (defensa en profundidad).

**Excepción:** Ninguna.

**Estado:** CONFIRMADO. Evidencia: `app/Services/WorkOrderService.php` (`assertTireCanEnterShop`), `database/migrations/2026_09_03_120000_add_work_order_items.php:16`.

### RN-005 — El recapado abre una vida nueva; la reparación (parche) no

**Regla:** Recapar cierra el `TireLifecycle` actual y abre uno nuevo; reparar (parche) mantiene el mismo `life_number` y solo cambia `condition` a `REPARADA`.

**Condición:** Cierre de OT Recapado, o `returnToStock(asRecap: true)`, vs. cierre de OT Reparación, o `returnToStock(asRecap: false)` desde `EN_REPARACION`.

**Resultado:** Nueva fila en `tire_lifecycles` (Recapado) vs. ninguna fila nueva (Reparación).

**Excepción:** Ninguna — es la única regla de esta lista con **dos implementaciones independientes** que, hasta donde se pudo verificar, respetan el mismo resultado (ver 0.2 para el riesgo que eso introduce igual).

**Estado:** CONFIRMADO en ambas vías por separado; DUPLICADO como implementación. Evidencia: `app/Services/IncidentService.php:88-99` (vía A), `app/Services/TireOperationService.php:471-483` (vía B), tests `tests/Feature/NonNegotiableRulesTest.php:63` y `tests/Feature/ProductionHardeningTest.php:195`.

### RN-006 — Toda baja requiere que la cubierta no esté montada

**Regla:** No se puede dar de baja una cubierta que figura instalada o en auxilio en una unidad.

**Condición:** `RetirementService::retire()` sobre una cubierta con `openAssignment` o `currentLocation.unit_id` no nulo, o `status` en `Instalada`/`Auxilio`.

**Resultado:** `DomainException` con instrucción de retirarla primero desde la planilla.

**Excepción:** Ninguna.

**Estado:** CONFIRMADO. Evidencia: `app/Services/RetirementService.php:96-121` (`assertNotMountedOnUnit`), tests `tests/Feature/RetirementModuleTest.php:84` (`test_store_rejects_tire_still_on_unit`).

### RN-007 — Solo puede haber una sesión de inventario activa por base

**Regla:** No se puede abrir una segunda `InventorySession` en una base que ya tiene una `OPEN`/`COUNTING`/`REVIEW`.

**Condición:** `InventoryService::open()`.

**Resultado:** `DomainException`.

**Excepción:** Ninguna.

**Estado:** CONFIRMADO. Evidencia: `app/Services/InventoryService.php:44-52`, test `tests/Feature/PhysicalInventoryTest.php:137`.

### RN-008 — El cierre de inventario nunca desmonta ni da de baja automáticamente

**Regla:** Aplicar correcciones al cerrar un inventario (`apply_fixes`) solo puede mover la **base** de cubiertas `WRONG_BASE`/`UNEXPECTED` que sigan en un estado "de depósito" (`STOCKABLE`). Nunca actúa sobre cubiertas `MISSING` ni `MOUNTED`.

**Condición:** `InventoryService::close(applyLocationFixes: true)`.

**Resultado:** Solo `BaseTransferService::transfer()` sobre las líneas elegibles; el resto queda auditado sin acción.

**Excepción:** Ninguna.

**Estado:** CONFIRMADO. Evidencia: `app/Services/InventoryService.php:248-266`, test `tests/Feature/PhysicalInventoryTest.php:58` (`test_wrong_base_can_be_corrected_on_close`) y `:91` (`test_mounted_tire_is_flagged_not_moved`).

### RN-009 — Toda cubierta activa tiene exactamente una ubicación actual

**Regla:** `tire_current_locations.tire_id` es único; no puede haber dos filas para la misma cubierta.

**Condición:** Siempre.

**Resultado:** Constraint de base de datos (`unique`) + `LocationService::place()` usa `updateOrCreate`.

**Excepción:** Ninguna.

**Estado:** CONFIRMADO. Evidencia: `database/migrations/2026_08_13_100000_create_traceability_tables.php:233`, test `tests/Feature/NonNegotiableRulesTest.php:31` (`test_rule_one_tire_one_current_location`).

---

## 13. INCONSISTENCIAS Y RIESGOS

### CRÍTICO

| ID | Severidad | Problema | Evidencia | Impacto | Solución propuesta |
|---|---|---|---|---|---|
| INC-01 | CRÍTICO | Dos caminos de código independientes para "esta cubierta fue recapada" | `WorkOrderService.php:130-136` vs `TireOperationService.php:444-497` | Reportes/auditoría inconsistentes según qué camino se usó | Ver DEC-001 |
| INC-02 | CRÍTICO | Cierre de OT tipo Recapado sin ningún test | `tests/` — única mención de `WorkOrderType::Recapado` no cubre `close()` | Un cambio futuro puede romper el recapado por OT sin aviso | Agregar test antes de tocar `WorkOrderService::close()` |

### ALTO

| ID | Severidad | Problema | Evidencia | Impacto | Solución propuesta |
|---|---|---|---|---|---|
| INC-03 | ALTO | `WorkOrder` sin relación a `fleet_units`; no representa montaje/desmontaje/rotación | Esquema de `work_orders` | El concepto "OT" del negocio está partido en dos entidades técnicas | Ver DEC-003 |
| INC-04 | ALTO | No se soporta "enviar N unidades de un stock/medida" sin elegir cada cubierta | `WorkOrderService::open()` firma `Tire\|iterable` | No cubre el escenario de compra/envío mayorista descripto en el pedido | Ver DEC-002 |
| INC-05 | ALTO | `docs/AUDIT.md` desactualizado (anterior a OT/Recapadoras) | `docs/AUDIT.md:288-292` | Riesgo de que alguien tome decisiones con información vieja | Actualizar o archivar ese documento cuando se implemente lo que salga de este informe |
| INC-06 | ALTO | Cierre de OT Recapado no devuelve la cubierta a `STOCK` (a diferencia de Reparación) | `WorkOrderService.php:130-141`, rama `elseif` excluye Recapado | Una cubierta recapada por OT puede quedar "perdida" en `EN_REPARACION` hasta una acción manual aparte | Ver DEC-004 |

### MEDIO

| ID | Severidad | Problema | Evidencia | Impacto | Solución propuesta |
|---|---|---|---|---|---|
| INC-07 | MEDIO | `AuditLog` sin protección de inmutabilidad | `AppServiceProvider.php` (sin `AuditLog::observe`) | El rastro de auditoría no está blindado como `TireMovement` | Agregar Observer análogo |
| INC-08 | MEDIO | Autorización de `returnToStock(asRecap)` partida entre Policy y Service | `TirePolicy.php:19-22` vs `TireOperationService.php:454-456` | Riesgo de regresión de permisos al tocar solo uno de los dos lugares | Documentar o mover el chequeo a un solo lugar |
| INC-09 | MEDIO | Formulario de medición en mobile solo manda 1 zona; el backend exige todas | `MeasurementService.php:29-42` vs `mobile/.../tires/[id].tsx` | La acción "Registrar medición" falla siempre desde el teléfono | Rehacer el formulario móvil (fuera del alcance de esta auditoría de negocio, pero se deja registrado) |
| INC-10 | MEDIO | `PurchaseService::confirm()` no llama `AuditService::log()` (solo `create()` lo hace) | `app/Services/PurchaseService.php` (confirmado por lectura; `confirm()` no tiene línea `audit->log`) | El paso que realmente crea las cubiertas (el de más impacto) no queda auditado explícitamente, más allá del propio `tire_movements` | Agregar `audit->log('purchase.confirmed', ...)` |

### BAJO

| ID | Severidad | Problema | Evidencia | Impacto | Solución propuesta |
|---|---|---|---|---|---|
| INC-11 | BAJO | `MovementType::FromSpare` definido pero nunca usado | `grep` sobre `app/Services` | Cosmético | Eliminar o implementar |
| INC-12 | BAJO | No existe estado/flujo de "recapado rechazado" | Sección 5 | El negocio no puede distinguir cancelación voluntaria de rechazo del taller | Evaluar si hace falta (no inventar sin confirmación) |

---

## 14. ARCHIVOS AFECTADOS

*(Solo se listan si el negocio confirma alguna de las decisiones de la sección 15. Ninguno de estos archivos se tocó durante esta auditoría.)*

### Backend

| Archivo | Cambio | Motivo | Riesgo |
|---|---|---|---|
| `app/Services/WorkOrderService.php` | Unificar/aclarar el cierre de Recapado (movimiento + retorno a stock) | INC-01, INC-06 | Alto — toca un flujo ya probado parcialmente |
| `app/Services/IncidentService.php` | Igualar la traza del recapado por OT a la de `returnToStock` (o viceversa) | INC-01 | Alto |
| `app/Providers/AppServiceProvider.php` | Registrar `AuditLog::observe(ImmutableRecordObserver::class)` | INC-07 | Bajo |
| `app/Policies/TirePolicy.php` | Reflejar el chequeo de `canRetireOrRecap()` para `asRecap` | INC-08 | Bajo |

### Web

*Ninguno identificado como obligatorio — la web ya refleja correctamente el Service.*

### App

| Archivo | Cambio | Motivo | Riesgo |
|---|---|---|---|
| `mobile/app/(tabs)/tires/[id].tsx` | Formulario de medición con todas las zonas | INC-09 (fuera del foco de negocio de este informe, mencionado por completitud) | Bajo |

### Base de datos

| Cambio | Motivo | Riesgo |
|---|---|---|
| Posible columna de referencia OT↔movimiento (si se resuelve DEC-001/DEC-003 a favor de unificar) | Trazabilidad | Medio — migración aditiva, no destructiva |

### Tests

| Archivo | Cambio | Motivo |
|---|---|---|
| `tests/Feature/ProductionHardeningTest.php` o nuevo `tests/Feature/WorkOrderRecapCloseTest.php` | Agregar `test_work_order_recap_close_opens_new_life_and_returns_to_stock` (o el nombre que corresponda una vez resuelto INC-06) | INC-02 |

---

## 15. PLAN DE IMPLEMENTACIÓN

*(No ejecutar todavía — depende de las decisiones de la sección 18.)*

### FASE 1 — Modelo y base de datos
Ninguna migración obligatoria si se decide DEC-001 a favor de "documentar, no unificar". Si se decide unificar, evaluar una columna de referencia (p. ej. `tire_movements.work_order_id` nullable) — aditiva, sin tocar `tire_operation_id` existente.

### FASE 2 — Backend
Resolver INC-06 (recapado por OT vuelve a stock), agregar test INC-02, decidir INC-08 (dónde vive el permiso de `asRecap`).

### FASE 3 — Web
Sin cambios obligatorios identificados.

### FASE 4 — App
Sin cambios obligatorios para los 5 dominios de este pedido (el hallazgo de medición, INC-09, es de una sesión de trabajo anterior, no de esta auditoría).

### FASE 5 — Inventario y trazabilidad
Evaluar si conviene enlazar `WorkOrder` a los `tire_movements` que genera indirectamente (mejora recomendada, no obligatoria).

### FASE 6 — Tests
INC-02 (cierre de OT Recapado) antes que cualquier otro cambio a `WorkOrderService`.

---

## 16. TESTS OBLIGATORIOS

| ID | Caso | Entrada | Resultado esperado |
|---|---|---|---|
| T-01 | Cerrar OT Recapado (una cubierta) | OT `EN_TALLER`, `type=RECAPADO`, 1 ítem | Vida nueva, `condition=RECAPADA`; **definir** si debe volver a `STOCK` (hoy no lo hace — ver DEC-004) |
| T-02 | Cerrar OT Recapado (lote de 2+) | OT `EN_TALLER`, `type=RECAPADO`, 2 ítems | Cada cubierta recibe su propia vida nueva; costo prorrateado (`CostService::splitCost`) |
| T-03 | Cancelar OT `ABIERTA` | OT recién creada | `status=CANCELADA`, `work_order_items.open_tire_id=null`, la cubierta sigue disponible para otra OT |
| T-04 | Cancelar OT `CERRADA` | OT ya cerrada | `DomainException`, sin cambios |
| T-05 | Doble apertura de OT para la misma cubierta | Cubierta ya en una OT abierta | `DomainException` (`assertTireCanEnterShop`) |
| T-06 | `returnToStock(asRecap: true)` por un rol sin `canRetireOrRecap` | Usuario Operario | `DomainException` |
| T-07 | `returnToStock(asRecap: true)` desde `RESERVA` (no `EN_REPARACION`) | Cubierta en Reserva | `DomainException` (`'El recapado se registra al volver de reparación...'`) |
| T-08 | Abrir inventario con una sesión ya activa en la base | Sesión `OPEN` existente | `DomainException` |
| T-09 | Escanear una cubierta ya escaneada en la misma sesión | Línea `found=true` | `DomainException` |
| T-10 | Cerrar inventario con `apply_fixes=true` sin permiso | Usuario sin `canChangeConfiguration` | `DomainException` |
| T-11 | Cerrar inventario con diferencia `MISSING` | Cubierta no escaneada | Línea marcada `MISSING`, **no** se mueve ni se da de baja |
| T-12 | Retirar (baja) una cubierta montada | `status=INSTALADA` | `DomainException` |
| T-13 | Doble baja de la misma cubierta | `status=DE_BAJA` | `DomainException` |
| T-14 | Movimiento inmutable — intentar `UPDATE`/`DELETE` sobre `tire_movements` | Cualquier fila | Excepción (trigger MySQL) u `DomainException` (Observer en SQLite/tests) |
| T-15 | Concurrencia: dos usuarios envían la misma cubierta a stock al mismo tiempo | Dos requests simultáneos | Solo uno debe tener éxito (a validar con `lockForUpdate`, no confirmado en un test de concurrencia real) |

*(Los casos ya cubiertos por tests existentes —RN-003, RN-004, RN-005 vía B, RN-006, RN-007, RN-008, RN-009— no se repiten acá; ver sección 12 para su evidencia.)*

---

## 17. DECISIONES QUE REQUIEREN CONFIRMACIÓN

### DEC-001 — Unificar o documentar los dos caminos de recapado

**Situación encontrada:** `WorkOrderService::close()` (vía A) e `TireOperationService::returnToStock(asRecap: true)` (vía B) implementan la misma regla de negocio ("recapado = vida nueva") de forma independiente, con distinta trazabilidad (`TireIncident` vs `TireMovement`) y distinta relación con taller/costo.

**Opciones posibles:**
A. Mantener ambos caminos, mejorando la vía A para que también deje un `tire_movements` (o la vía B para que también pueda opcionalmente referenciar una OT).
B. Eliminar la vía B para `asRecap: true` y forzar que todo recapado pase por una `WorkOrder` (más trazable, pero rompe el flujo rápido de "corregir la ficha" que hoy usan Jefe/Admin).
C. Dejarlo como está y documentarlo explícitamente como "dos flujos a propósito" (uno formal con taller, otro de corrección rápida).

**Impacto de cada opción:** A = cambio aditivo, bajo riesgo. B = cambio que remueve una funcionalidad existente usada y testeada (`NonNegotiableRulesTest`) — requiere confirmación explícita porque "no eliminar funcionalidades existentes sin justificarlo" es una instrucción directa del pedido. C = sin cambio de código, solo de documentación.

**Recomendación técnica:** A, si el negocio confirma que ambos flujos deben seguir existiendo.

**NO DETERMINADO — requiere confirmación del negocio antes de tocar código.**

### DEC-002 — ¿El negocio necesita operar "por cantidad" sin elegir cada cubierta?

**Situación encontrada:** El pedido describe explícitamente "Enviar X unidades de un stock a recapado" sin selección individual. El repositorio no tiene ningún precedente de esto — todo (compra, OT, operación de unidad) trabaja sobre `Tire` individuales, incluso cuando la compra originalmente es "por cantidad" (se individualiza al confirmar).

**Opciones posibles:**
A. Sí: agregar un modo "por cantidad" a `WorkOrderService::open()` que elija automáticamente N cubiertas `STOCK` de una medida/marca dada.
B. No: mantener selección individual, pero mejorar la UI para que elegir varias cubiertas de la misma medida sea rápido (filtro + selección múltiple), sin cambiar el modelo de datos.

**Impacto de cada opción:** A es un cambio de modelo de dominio real (afecta cómo se piensa "qué cubierta específica fue a recapar" — importante para la trazabilidad individual que el propio pedido pide preservar en la sección 2/3). B es solo UX, sin riesgo de modelo.

**Recomendación técnica:** B, salvo que el negocio confirme que la trazabilidad individual por recapado no importa para ese escenario puntual (lo cual contradice el resto del pedido, que insiste en preservarla).

**NO DETERMINADO — requiere confirmación explícita porque cambia una regla fundamental (individual vs. cantidad).**

### DEC-003 — ¿"Orden de Trabajo" debe unificar `WorkOrder` (taller) y `TireOperation` (planilla de unidad)?

**Situación encontrada:** El pedido describe una OT que cubre montaje/desmontaje/rotación/recapado/baja/etc. Hoy eso son dos entidades separadas, ninguna con ese alcance completo.

**Opciones posibles:**
A. Unificar bajo un solo concepto "Orden de Trabajo" (cambio de arquitectura significativo, toca `TireOperationService` que está fuertemente probado).
B. Mantener separadas, y solo mejorar la relación entre ambas (p. ej., que una OT de taller pueda "citar" la `TireOperation` que la originó, o viceversa).
C. Renombrar/reinterpretar solo a nivel de documentación/UI (sin tocar código): "Orden de Trabajo de taller" (`WorkOrder`) vs. "Operación de unidad" (`TireOperation`), dejando claro que son dos tipos de OT.

**Impacto de cada opción:** A es alto riesgo (toca el código más probado del sistema). B es bajo riesgo, aditivo. C es cero riesgo de código.

**Recomendación técnica:** C como paso inmediato, B como mejora incremental. A solo si el negocio confirma que necesita reportar/operar ambas cosas como una sola lista unificada.

**NO DETERMINADO.**

### DEC-004 — ¿Una cubierta recapada por Orden de Trabajo debe volver automáticamente a `STOCK`?

**Situación encontrada:** `WorkOrderService::close()` solo llama `returnToStock()` para `type=Reparacion`. Para `type=Recapado`, se registra el `TireIncident` (vida nueva, condición) pero la cubierta permanece con el `status` que tenía (probablemente `EN_REPARACION`, sin que ningún código visible la mueva a `STOCK` como parte del cierre).

**Opciones posibles:**
A. Es un bug: debería volver a `STOCK` igual que Reparación.
B. Es intencional: el negocio quiere un paso de inspección manual separado antes de devolverla a stock (lo cual coincide con el flujo pedido: `RECEPCIÓN → INSPECCIÓN → STOCK` como pasos distintos).

**Impacto de cada opción:** Si es A y no se corrige, cubiertas recapadas por OT pueden quedar "perdidas" operativamente (con condición correcta pero sin volver a estar disponibles). Si es B, no hay nada que corregir, pero conviene una acción explícita ("Recibir e inspeccionar") en vez de dejarlo implícito.

**Recomendación técnica:** No se puede determinar sin confirmación — el código no tiene un test que lo aclare, y ambas lecturas son plausibles.

**NO DETERMINADO.**

---

## 18. CONCLUSIÓN OBLIGATORIA

### LO QUE ESTÁ BIEN

- La inmutabilidad de `tire_movements` (trigger MySQL + Observer) — el punto más sólido de todo el repositorio.
- La separación entre Neumático, Ubicación actual y Movimiento — exactamente la separación que pide el punto 2 del pedido.
- El patrón `open_*_id` único + nullable para modelar "hay como mucho un X abierto por cubierta" (asignaciones, OT, acoples) — previene duplicaciones a nivel de base de datos, no solo de aplicación.
- El Inventario Físico (`InventoryService`) — snapshot teórico, conteo, diferencia, ajuste opcional sin tocar cubiertas montadas/faltantes, todo trazable y bien testeado.
- `IntegrityService` — un chequeo proactivo de inconsistencias (ubicaciones dobles, assignments duplicados, km negativo) que ya existe y funciona, aunque sea reactivo (hay que abrirlo) y no una constraint de base.
- Web y App comparten exactamente los mismos Services/Policies para los 5 dominios auditados — no hay lógica de negocio duplicada ni divergente entre plataformas.
- El manejo de roles/permisos por `Policy` + chequeo adicional en `Service` para reglas más finas (aunque genera el riesgo INC-08, el patrón en sí es razonable).

### LO QUE ESTÁ MAL

- El recapado tiene dos implementaciones independientes del mismo hecho de negocio (INC-01), y la más "oficial" (vía Orden de Trabajo) es la que menos se prueba (INC-02) y la que no devuelve la cubierta a stock automáticamente (INC-06).
- `docs/AUDIT.md` está desactualizado respecto del código actual (INC-05) — riesgo de decisiones basadas en información vieja.
- `AuditLog` no está protegido de edición/borrado como sí lo está `TireMovement` (INC-07).

### LO QUE FALTA

- Un test que cierre una Orden de Trabajo de tipo Recapado (INC-02).
- Un concepto de "recapado rechazado" (INC-12) — si el negocio lo necesita.
- Soporte para operar "por cantidad" sin seleccionar cada cubierta (DEC-002) — si el negocio lo necesita; hoy no existe en ningún punto del sistema.
- Relación explícita entre `WorkOrder` y los `tire_movements`/`TireOperation` que genera indirectamente.

### LO QUE DEBE CAMBIARSE

*(Condicionado a las decisiones DEC-001 a DEC-004 — nada de esto se implementa todavía.)*

- Resolver la duplicación del recapado (DEC-001) antes de tocar `WorkOrderService` o `TireOperationService` por cualquier otro motivo.
- Aclarar si `WorkOrder`/`TireOperation` deben unificarse conceptualmente (DEC-003), al menos a nivel de documentación, para que futuras decisiones no asuman que "OT" es una sola entidad.
- Agregar protección de inmutabilidad a `AuditLog` (INC-07) — cambio pequeño, bajo riesgo, alto valor.

### LO QUE NO DEBE TOCARSE

- `TireOperationService::execute()` (montaje/desmontaje/rotación) — fuertemente probado, con locks explícitos, y **no es el problema** que este informe encontró. Cualquier cambio motivado por "unificar el concepto de OT" (DEC-003, opción A) debe tratarlo con extremo cuidado si se decide tocarlo.
- El mecanismo `open_*_id` único (asignaciones, OT, acoples) — es la defensa contra duplicaciones más importante del sistema.
- `InventoryService` — completo y coherente con lo pedido; no se encontró ningún problema que justifique tocarlo.
- La separación `TireStatus` (ubicación/situación) vs. `TireCondition` (estado físico) — es correcta tal como está.

---

## Nota metodológica final

Este informe se basó en lectura directa de: la migración núcleo (`2026_08_13_100000_create_traceability_tables.php`, 436 líneas), la migración de OT/Recapadoras/Costos (`2026_08_19_200000_production_hardening.php`), las migraciones de inventario físico, work order items, triggers de inmutabilidad y endurecimiento de invariantes; los modelos y Services de los 5 dominios pedidos; las 3 Policies relevantes (`WorkOrderPolicy`, `InventorySessionPolicy`, `TirePolicy`); los 6 Observers registrados; los documentos internos ya existentes (`docs/INVARIANTES.md`, `docs/DOMAIN_INVARIANTS.md`, `docs/AUDIT.md`); y los archivos de test que mencionan estos dominios (`ProductionHardeningTest`, `NonNegotiableRulesTest`, `PhysicalInventoryTest`, entre otros). No se ejecutó el sistema ni los tests (sin motor de base de datos disponible en este entorno) — todos los hallazgos surgen de evidencia estática (código, esquema, tests leídos, no ejecutados).

Puntos marcados `NO DETERMINADO` que no llegaron a verificarse por límite de alcance de esta pasada (no por ambigüedad del código, sino porque no se leyeron en detalle): comportamiento exacto de `BaseTransferService::transfer()` línea por línea, si `IncidentService::register()` setea `started_by` de forma equivalente a la vía B, y el flujo completo de `ConfigurationChangeService`/`MovementCorrectionService`/`OpeningStockService` (se citan por nombre y por su único punto de creación de movimiento, no se leyeron completos). Ninguno de estos afecta las conclusiones críticas del informe, pero se listan para no afirmar más de lo verificado.
