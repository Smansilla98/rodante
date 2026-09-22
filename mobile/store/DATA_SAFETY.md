# Formulario "Seguridad de los datos" (Data safety) — guía para completarlo

Play Console pide este formulario dentro de la app (no se puede rellenar por API ni subir como archivo), así que
esto es una **guía exacta de qué tildar**, hecha revisando el código real de la app y del backend — no una
suposición genérica. Basada en:

- `mobile/src/auth/storage.ts` (qué se guarda en el celular)
- `app/Services/AuditService.php` (qué se audita en el servidor: IP + user-agent)
- ausencia confirmada de `expo-camera`, `expo-image-picker`, `expo-location` y de cualquier SDK de analítica/publicidad en `mobile/package.json`

## Paso 1 — ¿La app recopila o comparte alguno de los tipos de datos requeridos?

**Sí.**

## Paso 2 — Tipos de datos

| Categoría de Google | ¿Se recopila? | Detalle |
|---|---|---|
| **Ubicación** (precisa/aproximada) | **No** | La app no accede al GPS del dispositivo. Las "ubicaciones" que maneja el sistema (depósito, vehículo, posición) son datos de negocio que carga el usuario, no la posición real del teléfono. |
| **Información personal → Nombre** | Sí | Nombre del usuario de la cuenta (lo carga el administrador de la empresa). |
| **Información personal → Dirección de email** | Solo si la empresa usa email como usuario | Depende de cómo cada empresa configure el login (usuario vs. email); si usan email, declarar que sí. |
| **Información personal → ID de usuario** | Sí | Usuario/legajo dentro de la empresa. |
| **Información personal → Otra info** | Sí | Rol dentro de la empresa y flotas/depósitos asignados. |
| **Datos financieros** | Sí (limitado) | Costos de órdenes de trabajo/compras que el usuario carga como parte de su trabajo (no son datos financieros personales del usuario de la cuenta, sino de la operación de la empresa). Declarar como "Otra información financiera". |
| **Fotos y videos** | **No, desde la app móvil** | El backend admite fotos de baja de neumáticos, pero esa carga es una función solo del sistema web hoy — la app móvil no accede a la cámara ni a la galería (no tiene `expo-camera` ni `expo-image-picker` instalados). Si en el futuro se agrega esa función al móvil, hay que actualizar esta declaración. |
| **Archivos y documentos** | No | — |
| **Contactos** | No | — |
| **Calendario** | No | — |
| **Identificadores de dispositivo u otros** | Sí | Dirección IP y user-agent, registrados junto con cada acción crítica para el historial de auditoría interno de la empresa (no para publicidad ni identificación entre apps). |
| **Historial de compras dentro de la app** | No | La app no vende nada ni tiene compras integradas. |
| **Registros de uso / interacción con la app** | No | No hay SDK de analítica instalado. |
| **Información de diagnóstico (fallos, rendimiento)** | No, salvo que se agregue un SDK de crash-reporting más adelante | Hoy no hay Sentry/Crashlytics ni similar instalado. Si se agrega, actualizar esta declaración. |

## Paso 3 — Para cada tipo marcado "sí"

Para **todos** los ítems marcados que sí se recopilan, las respuestas correctas al resto de las preguntas de Play Console son:

- **¿Estos datos se comparten con terceros?** No (el único proveedor externo es el hosting donde corre el servidor, que Google no considera "compartir con terceros" en el sentido del formulario si el hosting no usa los datos con fines propios).
- **¿Es obligatorio o pueden optar por no darlos?** Obligatorio — son datos necesarios para usar la app (login y operación).
- **¿Para qué se usan?** "Funcionalidad de la app" (App functionality) para todos. Para IP/user-agent, también "Seguridad de la cuenta" (Account management / Fraud prevention, security).
- **¿Los datos están cifrados en tránsito?** Sí (HTTPS).
- **¿Los usuarios pueden pedir que se borren sus datos?** Sí, a través del administrador de su empresa (no hay autoservicio de baja de cuenta dentro de la app — ver `privacy-policy.html`, sección 8).

## Lo que NO hay que declarar (y por qué)

- **Publicidad o marketing**: no hay SDKs de publicidad ni de tracking cross-app. No tildar ningún propósito de "Advertising or marketing".
- **Analítica de terceros**: no hay Firebase Analytics, Amplitude, Mixpanel ni similar instalado (`mobile/package.json` no los tiene).
- **Ubicación**: reforzando el punto de arriba — no confundir "ubicación del depósito/vehículo" (dato de negocio) con "ubicación del dispositivo" (lo que pregunta Google). Son cosas distintas.

## Nota

Esta guía es una ayuda para completar el formulario correctamente, no un reemplazo de hacerlo: el formulario en
sí vive dentro de Play Console y solo lo puede completar quien tiene acceso a esa cuenta.
