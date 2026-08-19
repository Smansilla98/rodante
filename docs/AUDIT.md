# Auditoría — Rodante

**Producto:** Rodante — sistema inteligente de gestión y trazabilidad de neumáticos para flotas  
**Repositorio:** [github.com/Smansilla98/rodante](https://github.com/Smansilla98/rodante)  
**Fecha:** 18 de agosto de 2026  
**Alcance:** 100 % del código de aplicación (backend, frontend, DB, tests, Docker, deploy, docs).  
**Método:** lectura de rutas, servicios, modelos, migraciones, vistas, JS/CSS, tests y configuración. No se modificó comportamiento de negocio en esta fase.

**Motor de datos actual:** MySQL 8 (local Docker y Railway recomendado). PostgreSQL es alternativa de deploy, no el default del producto.

---

## 1. Arquitectura actual

### 1.1 General

Laravel 13 / PHP 8.3 / Blade / Tailwind 4 / Vite / MySQL 8 / PHPUnit / Docker (nginx + php-fpm + MySQL).

Arquitectura de aplicación delgada: **controladores validan y delegan; la regla de negocio vive en `app/Services`**. No hay repositorios, Policies, Form Requests, Events ni Actions. Eso es deliberadamente simple y, para el tamaño actual, adecuado — con huecos de autorización en la API.

```
Browser / cookie session
        │
   routes/web.php  ── capability:* / role:ADMINISTRADOR
   routes/api.php  ── auth:web  (sin capabilities)
        │
   Controllers  →  Services  →  Eloquent + unique keys
        │
   Blade + resources/js/app.js  (planilla / recambio)
```

### 1.2 Capas y responsabilidades

| Capa | Ubicación | Responsabilidad |
|---|---|---|
| HTTP web | `app/Http/Controllers/*` | Validación inline, flash, redirección |
| HTTP API | `app/Http/Controllers/Api/TireApiController.php` | JSON; **sin** validación ni capabilities |
| Autorización | `EnsureCapability`, `EnsureRole`, checks puntuales en algunos services | Matriz de roles |
| Dominio | `app/Services/*` | Operación, km, acoples, recap, baja, compras, reportes |
| Catálogo estático | `app/Support/UnitConfigurationCatalog`, `TireProductCatalog` | Layouts y productos |
| Persistencia | Eloquent + migraciones | Unicidad de ubicación / assignment abierto vía `open_key` |
| UI | Blade components + CSS propio (~2560 líneas) | Dark navy + rojo de marca, restos lila |
| QA | `php artisan qa:roles` + Feature tests | HTTP por rol + dominio |

### 1.3 Flujo de usuario

1. Login (`admin` / `password` en demo).
2. Tablero con 8 KPI clicables.
3. Trabajo diario del operario: **Unidades → planilla** (montar, rotar, pinchadura, medir).
4. Consulta/auditoría: búsqueda de cubierta → ficha.
5. Jefe: misma planilla + baja + acople + cambio de configuración + corrección de odómetro.
6. Admin: Catálogo y ABM.

La ficha **no** es el centro operativo. La planilla sí.

### 1.4 Flujo de un neumático

```
Compra confirmada
    → STOCK + vida 1 (TireLifecycle)
    → INSTALADA (posición de rodaje) o AUXILIO
    → rotación (mismo assignment, km abierto)
    → retiro SIEMPRE a STOCK (y opcional Reserva / En reparación)
    → recapado (cierra vida, abre vida N+1, STOCK)
    → reparación (cambia condición; NO abre vida)
    → baja (cierra vida, DE_BAJA; no se vuelve a montar)
```

Historial: `tire_movements` + `tire_incidents` + `tire_measurements` + `tire_lifecycles` + `tire_assignments` / `tire_assignment_segments`.  
La identidad histórica es el **neumático** (`individual_number`), no la patente.

### 1.5 Flujo de eventos

Una operación de planilla (`TireOperationService::execute`) corre en transacción con `lockForUpdate`:

- crea `tire_operations`
- mueve `tire_current_locations`
- crea `tire_movements`
- abre/cierra `tire_assignments` y segmentos
- registra odómetro
- escribe `audit_logs`

Los movimientos **no tienen `updated_at`** (bien para inmutabilidad visual). No hay trigger ni observer que impida un UPDATE.

### 1.6 Relación vehículo / neumático / posición / ubicación

- `fleet_units` + `unit_configurations` + `unit_positions` = mapa del chasis.
- `tire_current_locations`: **una fila por cubierta** (`tire_id` unique). `(unit_id, position_id)` unique para huecos ocupados.
- Tractor + semi es **vista operacional** (`unit_couplings`). El acoplado usa el odómetro del tractor mientras está unido (`CouplingService::resolveOdometerUnit`).
- Semi/tanque/batea **sin acople** no pueden asentar km.

### 1.7 Roles

| Rol | Escritura | Acoples | Odómetro | Baja/recap | Config ejes | Catálogo |
|---|---|---|---|---|---|---|
| Administrador | sí | sí | sí | sí | sí | sí |
| Jefe de sector | sí | sí | sí | sí | sí | no |
| Logística | sí | sí | sí | no | no | no |
| Operario | sí | no | no | no | no | no |
| Consulta | no | no | no | no | no | no |

`user_fleet_access` / `user_base_access` se cargan en el ABM y **no filtran ningún listado**.

---

## 2. Fortalezas

1. Dominio partido en servicios con `DomainException` y mensajes en castellano.
2. Invariante “un neumático, una ubicación” reforzado en DB (`tire_current_locations.tire_id` unique) + `lockForUpdate`.
3. Un hueco, una cubierta (`unique(unit_id, position_id)`).
4. Assignment/segmento abierto modelado con `open_key` unique.
5. Retiro pasa por STOCK en código (`removeToStock`).
6. Recap abre vida; reparación no. Cubierto por test.
7. Rotación **no** cierra el assignment (periodo de km abierto). Cubierto por test.
8. Auxilio **al instalar** no suma km (`counts_km = ! is_spare`).
9. Historial atado a `tire_id`; tractor+semi es vista.
10. Odómetro del acoplado = tractor acoplado. Recople parte segmentos. Tests.
11. Fit de aplicación (dirección/tracción) y ancho lineal 295/385.
12. Soft-delete de negocio: catálogos/unidades/usuarios con historial se desactivan, no se borran.
13. Ayuda in-app por rol + manual Markdown.
14. QA HTTP por rol (`qa:roles`) y Feature tests densos de dominio (`TireTraceabilityTest`).
15. Proxies, `APP_DEBUG=false` y cookie secure en `.env.railway`.
16. Identidad visual base (navy `#101820`, rojo `#C8102E`) y skip-link / drawer con Escape.

---

## 3. Problemas

### CRITICAL

| ID | Problema | Evidencia | Impacto |
|---|---|---|---|
| C1 | **API sin capabilities.** Un usuario Consulta autenticado puede montar/retirar vía `/api/v1`. | `routes/api.php`, `TireApiController::operate` usa `$request->all()`. `TireOperationService` no chequea rol. | Trazabilidad falsa: movimientos que el rol no puede hacer en la UI. |
| C2 | **Rotar a auxilio no apaga `counts_km`.** | `TireOperationService` ~185–186 vs install ~322–339. | Auxilio acumula km de rodaje. Reportes y “próximas a baja” mienten. **Contradice regla 5.** |
| C3 | **Corregir odómetro no recalcula km de cubiertas** y **reescribe** `tire_operations.odometer`. | `OdometerService::update`. | Km de cubierta ≠ reloj. Además muta un evento operativo. **Tensión con regla 7.** |
| C4 | **En reparación / reserva no vuelven a stock.** `isInstallable()` solo STOCK. `FromRepair` / `FromReserva` no están implementados. | `TireStatus.php`, pinchadura en `UnitController`. | Callejón: o se recapa (vida nueva, incorrecto para un parche) o la cubierta queda eterna en reparación. |
| C5 | **`kmFmt` no existe** en la planilla. | `resources/js/app.js` ~336 `fillFacts()`. | `ReferenceError` al tocar una cubierta montada. Camino principal del operario. |
| C6 | **Login precarga `admin` / `password`.** | `auth/login.blade.php`. | Credenciales en el DOM; peligroso si el mismo código va a Railway con `SEED_DEMO=1`. |
| C7 | **Ficha no muestra el historial real.** Timeline partida (movimientos luego incidencias, sin fusionar por fecha). Mediciones, vidas y usuario se cargan y no se pintan. | `tires/show.blade.php`, `ReportService::tireHistory`. | Un jefe no audita una cubierta en una pantalla. |

### HIGH

| ID | Problema |
|---|---|
| H1 | Acceso por flota/base es cosmética (`User::canAccessFleet` nunca se usa). |
| H2 | Pinchadura/cambio: incidencia y operación en transacciones distintas. Si `execute` falla, queda incidencia huérfana. |
| H3 | Cambio de configuración cierra km con `current_odometer` sin pedir lectura validada. |
| H4 | Recapado visible en la ficha para cualquier `canWrite`; el backend lo bloquea (error confuso). |
| H5 | Login sin throttle. `SEED_DEMO` default 1 en Railway. |
| H6 | Admin puede editar `individual_number` con historial (identidad de taller mutable). |
| H7 | Históricos `$fillable`; no hay observer de inmutabilidad. |
| H8 | API sin Form Request: datos basura → 500 o línea de tiempo corrupta. |
| H9 | Baja definitiva, confirmar compra y cambio de config **sin confirmación**. |
| H10 | Sin páginas 403/404/500 de marca. |
| H11 | Tablero: KPIs clicables pero poco accionables; `by_condition` se calcula y no se muestra. |
| H12 | Búsqueda global solo cubiertas por número/código; no patente. |

### MEDIUM

| ID | Problema |
|---|---|
| M1 | `is_active` solo se chequea al login; sesión vigente sigue operando. |
| M2 | Password mínimo 6. |
| M3 | Timezone `UTC` para operación AR. |
| M4 | `PurchaseService::nextNumber()` = `count()+1` (carrera). |
| M5 | Planilla carga **todo** el stock en memoria. Reportes km/consumo sin paginar. |
| M6 | N+1 en dashboard (FleetUnit::find en loop), rotación, openSegments. |
| M7 | Índices faltantes: `tires(accumulated_km)`, `tires(current_tread_min)`, segmentos abiertos, mediciones. |
| M8 | Unique parcial de assignment abierto sustituido por `open_key`: si `ended_at` queda null y `open_key` null, caben dos abiertos. |
| M9 | Sistema visual: restos lila (`#c4b5fd`), KPI rainbow, Inter 800 no cargada (400–700). |
| M10 | Sin anti doble submit / loading. Error de validación: un solo flash, casi nunca por campo. |
| M11 | Empty states incompletos; catálogos sin paginar; tablas en móvil = scroll horizontal. |
| M12 | Docker entrypoint `migrate \|\| true` y `seed \|\| true`. Imagen FPM no es de producción. Logs Railway a archivo, no stderr. |
| M13 | `AppServiceProvider` usa paginador Bootstrap 5 en UI Tailwind. |

### LOW

| ID | Problema |
|---|---|
| L1 | `UnitController::rotate` sin ruta. `capability:abm` sin uso. |
| L2 | `welcome.blade.php`, `inspire`, localStorage `rodanta-scale` / `tn-scale`. |
| L3 | Enums de movimiento huérfanos (`TransferBase`, `FromSpare`, …). `OdometerStatus::Pending` muerto. |
| L4 | `@section('kicker')` nunca se hace yield. `x-content-table :small` no aplica clase. |
| L5 | Condición (Nueva/Recapada) sin badge; status INSTALADA vs lenguaje “montado”. |

### IMPROVEMENT (producto, no bug)

- Timeline unificada en ficha; dashboard tipo inbox; breadcrumbs; búsqueda patente+cubierta; design tokens únicos; cards en móvil; toasts; impresión de ficha; export CSV; QR; costos (explícitamente fuera de v1); multiempresa.

---

## 4. Deuda técnica

1. Autorización repartida (middleware web ≠ API ≠ algunos services). Falta una sola puerta (Policy o check en cada service de escritura).
2. Validación 100 % inline; cero Form Requests.
3. `LocationKind` ≡ `TireStatus` (mismos strings).
4. `canManageCatalogs()` ≡ `canManageAbm()`.
5. Data migrations Eloquent (`150000`–`190000`) con `down()` vacío; 120000 es no-op en install fresco.
6. Factories: solo `UserFactory`. Tests acoplan a `CatalogSeeder`.
7. Suite Unit vacía. Tests solo sqlite en memoria (paridad MySQL no se prueba).
8. Cero `Log::` de dominio. `DomainException` sin `render()` global.
9. CSS monolítico. Componentes Blade bien factorizados en planilla; ABM `?edit=1` copiado en 10 vistas.

---

## 5. Riesgos (trazabilidad y negocio)

| Riesgo | Cómo se materializa | Severidad |
|---|---|---|
| Pérdida de trazabilidad | API Consulta inserta movimientos | CRITICAL |
| Km incorrectos | Rotación a auxilio; edición de odómetro | CRITICAL |
| Asignaciones imposibles | Unique de posición + fit lo evitan en web; API sin validar payload | HIGH |
| Eventos históricos incorrectos | Incidencia huérfana (pinchadura); rewrite de `tire_operations.odometer` | HIGH |
| Cubierta “fantasma” | EN_REPARACION sin retorno → stock miente | HIGH |
| Identidad mutable | Cambio de `individual_number` | HIGH |
| Permisos incorrectos | Flota/base no filtra; recap visible a operario | HIGH |
| Datos duplicados | `nextNumber` count+1; unique de compra salva con 500 | MEDIUM |
| Estados imposibles en DB | No hay CHECK `INSTALADA` ⇒ unidad; `open_key` vs `ended_at` | MEDIUM |

### Contradicciones con reglas inviolables (no se corrigen en silencio)

**Regla 5 — Auxilio no suma km.**  
Implementada en **instalación**. **Rota** a auxilio deja `assignment.counts_km` / `segment.counts_km` como estaban. Es un **bug**, no una regla nueva. Corrección P0: al rotar, alinear `counts_km` con `is_spare` de la posición destino, **sin cerrar** el assignment (regla 6).

**Regla 7 — Eventos históricos inmutables.**  
La UI no edita movimientos, pero `OdometerService::update` pisa `tire_operations.odometer`. El manual dice: se corrige la **lectura**; el rastro queda. Opción segura: no mutar la operación; recalcular `km_delta` de segmentos afectados y dejar audit log. Se documenta en ROADMAP P0; no se reescribe historia de `tire_movements`.

**Códigos de ejes inexistentes.**  
`6X24`, `6X1`, `7X24` y `6X1X1` no son configuraciones del producto ni layouts “pendientes”. El catálogo real es `4X2`, `4X4`, `6X2`, `6X2-P`, `6X4`, `6X6`, `8X2`, `8X4`, `8X8`, `10X4`, `10X6` y acoplados `1E`–`5E` / `3E-1S2D`. No se van a cargar aquellos códigos.

**Retorno desde reparación.**  
El enum `MovementType::FromRepair` existe; el flujo no. Completarlo es **cerrar un hueco ya anunciado en el modelo**, no inventar una regla. Debe: desmontar ya ocurrió (está en EN_REPARACION) → volver a STOCK **sin** abrir vida (regla 4).

---

## 6. UX/UI (usuario real de transporte)

| Pregunta | Respuesta hoy |
|---|---|
| ¿Entiendo dónde estoy? | Parcial. Sidebar con `aria-current`. Sin breadcrumbs. Kicker de sección no se muestra en el layout. |
| ¿Sé qué puedo hacer? | En planilla, sí (dock). En tablero, no: mismos KPI para operario y jefe. Acciones de jefe (acople, config, baja) enterradas. |
| ¿Sé qué acabó de pasar? | Flash único. Sin toast, sin loading, sin “se guardó” en JS. |
| ¿Estados claros? | Badge de status sí. Condición (Nueva/Recapada) texto plano. INSTALADA ≠ “montado”. |
| ¿Acción principal visible? | En planilla sí. En ficha, baja comparte panel con medición. |
| ¿Encuentro un neumático rápido? | Topbar busca número. No busca `FH:01 Nº30363` completo ni patente. |
| ¿Detecto problemas? | Tabla “próximas a baja” (10 filas, umbral 80.000 km / 4 mm **sin explicarlo**). KPI de reparación no está destacado como cola. |
| ¿Entiendo el historial? | **No.** Timeline partida, incompleta. |
| ¿Opero de tablet/celular? | Consulta sí. Operación de campo (drag, menú derecho, tap &lt; 44 px) no. |
| ¿Demasiados clics? | Operario: Tablero → Unidades → patente → slot. Aceptable. Auditoría: ficha incompleta fuerza a reportes. |
| ¿Info crítica escondida? | Acoplar/config al pie de planilla. Umbrales del tablero. Usuario del movimiento. |

Veredicto de las tres preguntas de producto:

- ¿Un operador nuevo aprende rápido? **Planilla sí, con el bug de `kmFmt` no.**  
- ¿Un jefe ve lo importante en 10 s? **No.** El tablero no es un inbox.  
- ¿Un admin audita una cubierta sin 15 pantallas? **No.** La ficha no concentra el historial.

---

## 7. Seguridad (resumen)

- CSRF web OK. API cookie `auth:web` sin capabilities.
- Mass assignment amplio en modelos históricos.
- Sin throttle de login.
- Usuario inactivo no se expulsa.
- `SEED_DEMO=1` por default en Railway.
- Trust proxies `*`: correcto detrás de Railway; no exponer FPM a internet.
- XSS: Blade escapa; JS arma DOM con `textContent` (bien) en facts.

---

## 8. Tests — cobertura vs huecos

**Hay:** install/remove, pasaje por stock, spare al instalar, rotación no cierra km, recap vs repair, acople/segmentos, fit, ancho lineal, pinchadura, ABM admin, login, ayuda, QA HTTP 5 roles.

**Falta:** invariante 1 ubicación / 1 assignment abierto; rotación a auxilio ⇒ `counts_km=false`; recap/baja 403 operario; API 403 consulta; retorno reparación; flota/base; odometer edit ⇒ km; usuario inactivo; carrera de posición.

---

## 9. Deploy / Docker

Local: `docker compose` usable (puerto 8093). Entrypoint traga errores de migrate/seed. Dockerfile.fpm no empaqueta la app (solo volume). Railway: `railway.toml` solo release; start lo decide Nixpacks. Script de DB sólido. Logs a `storage/logs`, no stderr.

---

## 10. MVP actual vs lo que falta para vender

**MVP actual (funciona en demo local):** planilla, stock, compras, acoples, odómetros, recap/baja, reportes, roles, ayuda, QA.

**Para usar todos los días en una empresa real (necesario):** invariantes de km, retorno de reparación, API segura, ficha auditable, tablero accionable, confirmaciones, flota/base, deploy reproducible, tests de invariantes.

**Futuro (no implementar ahora):** multiempresa, costos, QR, importación masiva, PDF, órdenes de trabajo, recapadoras, preventivo avanzado.
