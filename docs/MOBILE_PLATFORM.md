# Rodante — plataforma Web + App nativa

## Decisiones

| Tema | Decisión |
|------|----------|
| Mobile | **Expo (React Native)** en `/mobile` — una codebase Android/iOS, EAS → AAB/IPA |
| Backend | Monolito Laravel intacto (no se mueve, no se tocan rutas fuera de `routes/api.php`) |
| API canónica | **Sanctum** (Bearer token único, 30 días, sin refresh) — `routes/api.php`, prefijo `/api/v1`. Devuelve JSON crudo de Eloquent (sin envelope `{success,data}`); listados paginados usan el paginator por defecto de Laravel (`{data, links, meta}`) |
| Web desktop | Blade + sesión (sin rewrite SPA) |
| Navegación | **Bottom tabs** nativos — 5 accesos (Inicio, Buscar, Neumáticos, Unidades, Más); Órdenes de trabajo, Inventario, Telemetría\* y Perfil se ofrecen desde "Más" (rutas normales, ocultas de la barra con `href: null`); excepción de UX nativa, no reemplaza nada del sidebar web |
| Diseño mobile | Pensado para trabajadores de campo (mecánicos/operarios de depósito) no necesariamente habituados a apps: texto grande (piso 15px, cuerpo 18px), objetivos táctiles de 56px (filas de lista 72px), sin mayúsculas forzadas, estado comunicado con ícono + color (no solo color), confirmaciones en lenguaje simple para acciones irreversibles. Ver `mobile/src/theme.ts` (`type`, `space`, `touchTarget`) y `mobile/src/ui/primitives.tsx` |
| Fotos de incidente/baja | **Excluidas de mobile v1**: `TirePhotoController` existe en el backend web pero no está expuesto en `routes/api.php`. Reevaluar cuando se agregue un endpoint API de subida de fotos (multipart) |
| Reasons/zonas de medición | Los formularios de incidente/medición/retiro piden IDs numéricos (`reason_id`, `zone_id`) porque no existen endpoints `GET /movement-reasons` ni `GET /measurement-zones` en la API v1. Reevaluar cuando se agreguen esos catálogos a la API |
| Roles → capacidades | Espejo cliente de `App\Enums\UserRole` (`mobile/src/auth/permissions.ts`) solo para gating de UI. La autorización real es siempre server-side (`capability:*` middleware + Gates) |

## Arquitectura

```text
trazabilidad-neumaticos/   Backend + web Blade
  app/Http/Controllers/Api/  Sanctum REST (TokenController, ApiSurfaceController, TireApiController)
  routes/api.php              Superficie API v1 completa
mobile/                       Expo app (Android + iOS)
  app/                        Expo Router (tabs + stacks anidados)
  src/api/                    Cliente HTTP + tipos
  src/auth/                   SecureStore token + matriz de capacidades
  src/theme.ts                Tokens de resources/css/app.css (tema oscuro)
```

## Entornos mobile

| Profile EAS | Variable | Uso |
|-------------|----------|-----|
| development | `EXPO_PUBLIC_API_URL` | API local/túnel |
| preview | `EXPO_PUBLIC_API_URL` | staging HTTPS |
| production / production-apk | `EXPO_PUBLIC_API_URL` | prod HTTPS |

**Nunca** embeber secretos de servidor (DB, `APP_KEY`, etc.) en el binario. El único
secreto en el dispositivo es el token Sanctum del usuario logueado, guardado en
Expo SecureStore.

## Identificadores de app

| Campo | Valor |
|-------|-------|
| Nombre | Rodante |
| Android package | `com.rodant.app` |
| iOS bundle | `com.rodant.app` |
| Scheme | `rodante` |

## Auth móvil

1. `POST /api/v1/auth/token` `{username, password, company_id?, device?}` → `{token, token_type, expires_at, user}` (`TokenController::store`)
2. Token Bearer en **Expo SecureStore** (`rodante_token`) — **no hay refresh token**, vigencia fija de 30 días
3. `DELETE /api/v1/auth/token` revoca el token actual (`TokenController::destroy`)
4. Un 401 en cualquier request limpia el token local y redirige a `/login` — no hay reintento con refresh porque el backend no lo soporta
5. Si el mismo `username` existe en más de una empresa, el login devuelve 422 pidiendo `company_id`; la pantalla de login muestra un campo adicional en ese caso

## Matriz de capacidades (`App\Enums\UserRole`)

Reflejada en `mobile/src/auth/permissions.ts`. Gating de UI únicamente — la
autorización real es el middleware `capability:*` (`app/Http/Middleware/EnsureCapability.php`)
y los Gates de modelo; un 403 del servidor siempre puede ocurrir aunque la UI lo
permita, y debe mostrarse como aviso (no asumirse imposible).

| Capacidad | Administrador | Jefe de sector | Logística | Operario | Consulta |
|-----------|:---:|:---:|:---:|:---:|:---:|
| `canWrite` (incidente/medición/devolución) | sí | sí | sí | sí | — |
| `canValidateOdometer` | sí | sí | sí | — | — |
| `canManageAbm` | sí | — | — | — | — |
| `canRetireOrRecap` (dar de baja / recapar) | sí | sí | — | — | — |
| `canViewTelemetry` | sí | sí | — | — | — |
| `canChangeConfiguration` | sí | sí | — | — | — |
| `canManageCouplings` | sí | sí | sí | — | — |

## Checklist de validación

Probar contra API desplegada tras `relogin` por rol.

| Módulo | Administrador | Jefe de sector | Logística | Operario | Consulta |
|--------|:---:|:---:|:---:|:---:|:---:|
| Dashboard (KPIs por rol) | sí | sí | sí | sí | sí (subset) |
| Cubiertas (listado/filtro/detalle) | sí | sí | sí | sí | sí |
| Cubiertas (incidente/medición/devolución) | sí | sí | sí | sí | — |
| Cubiertas (dar de baja) | sí | sí | — | — | — |
| Buscar (lookup por número/DOT) | sí | sí | sí | sí | sí |
| Unidades (layout + operar posiciones) | sí | sí | sí | sí | lectura |
| Órdenes de trabajo (listar/crear) | sí | sí | sí | sí | lectura |
| Inventarios (listar) | sí | sí | sí | sí | sí |
| Telemetría | sí | sí | — | — | — |
| Perfil + logout | sí | sí | sí | sí | sí |

## Criterio de listo

- CI backend verde + job `mobile` (typecheck + tests) verde en push
- Superficie API v1 cubre auth + operaciones de campo del MVP
- EAS profiles configurados (`eas.json`: development / preview / production / production-apk)
- Assets de ícono/splash generados desde `public/brand/` en `mobile/assets/`
- App MVP usable contra el backend real de producción

## URL de producción

`https://rodant-production.up.railway.app` (Railway). API base: `https://rodant-production.up.railway.app/api/v1`.
`mobile/app.config.ts`, `mobile/src/config.ts`, `mobile/eas.json` (perfiles preview/production/production-apk)
y `.env.railway` ya la usan como fallback/valor por defecto.

## Pendiente / fuera de alcance de este MVP

- **`eas init` real**: no existe todavía un proyecto EAS para Rodante. `app.config.ts`
  deja `extra.eas.projectId` sin setear a propósito — nunca se fabrica un UUID que
  parezca real.
- **Fotos de incidente/baja**: `TirePhotoController` (web) no está expuesto en
  `routes/api.php`. Agregar un endpoint API multipart antes de habilitarlo en mobile.
- **Catálogo de `zone_id`**: no hay endpoint API para listar `measurement_zones`;
  el formulario de medición todavía pide el ID numérico directamente. `GET /v1/movement-reasons`
  ya existe (`ApiSurfaceController::movementReasons`) y la pantalla de unidades lo usa
  para el selector de motivo al retirar un neumático — falta el equivalente de zonas.
- **Push notifications**: `expo-notifications` está en el bundle pero no hay backend
  de registro de dispositivos (`POST /v1/devices`) en Rodante — no se activó el
  registro automático en `AuthContext`.
- **Offline queue**: no implementada en este MVP (el patrón existe en Conurbania,
  `mobile/src/offline/queue.ts`, como referencia si se necesita más adelante).
