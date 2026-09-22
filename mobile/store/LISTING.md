# Ficha de Play Store — Rodante

Textos listos para copiar/pegar en Play Console → *Presencia en la tienda → Ficha de la app*.

## Título

```
Rodante
```

## Descripción breve (65/80 caracteres)

```
Gestión de neumáticos y flota: stock, movimientos, OT y recapado.
```

## Descripción completa (1604/4000 caracteres)

```
Rodante es el sistema de gestión de neumáticos y flota para empresas de transporte y logística: cada cubierta se identifica de forma individual (número interno, DOT) y su historial completo queda registrado de punta a punta, desde que ingresa a stock hasta que se da de baja.

QUÉ PODÉS HACER DESDE LA APP

• Consultar el stock disponible por depósito y buscar cualquier neumático por número, DOT o vehículo.
• Ver la ficha completa de cada cubierta: ubicación actual, condición, historial de movimientos, incidentes y mediciones de profundidad de banda.
• Registrar movimientos desde el celular: montaje, desmontaje y rotación sobre la unidad, con el kilometraje de cada tramo.
• Cargar incidentes (pinchadura, parche, desgaste irregular, inspección) y mediciones de profundidad zona por zona.
• Gestionar Órdenes de Trabajo con talleres y recapadoras: abrir, enviar al taller y cerrar, con costo y taller asociado.
• Hacer inventarios físicos por depósito: conteo contra lo esperado, diferencias y ajustes, todo trazable.
• Dar de baja neumáticos y consultar el reporte de vida completo (costos, kilometraje, historial).

PENSADA PARA EL DÍA A DÍA EN EL DEPÓSITO Y LA RUTA

Roles y permisos por usuario (consulta, operario, logística, jefe de sector, administrador), acceso restringido por empresa, flota y depósito, y un historial de movimientos que una vez registrado no se puede alterar ni borrar — solo corregir con un nuevo evento, quedando ambos a la vista.

Rodante es una aplicación de uso interno: se accede con una cuenta que crea el administrador de tu empresa. No requiere registro público.
```

## Categoría sugerida

`Negocios` (Business) — es la categoría de Play Store que mejor describe una herramienta interna B2B de gestión de flota. No es `Herramientas`, `Productividad` ni ninguna categoría de consumo masivo.

## Clasificación de contenido

Al completar el cuestionario de clasificación de contenido en Play Console, corresponde declarar: sin violencia, sin contenido para adultos, sin interacción entre usuarios ni compras dentro de la app, sin publicidad. Debería calificar como **PEGI 3 / Para todo público**, pero la clasificación final la asigna el cuestionario oficial de Google, no este documento.

## Público objetivo

Se recomienda declarar el público objetivo como **18+ / uso profesional**, no un rango etario general, porque es una herramienta operativa de empresas, no una app para el público general. En el formulario de "Target audience" de Play Console, seleccionar que no está dirigida a niños.

## Gráficos requeridos

| Pieza | Tamaño | Estado |
|---|---|---|
| Ícono de la app | 512×512 | Listo — `mobile/store/play-store-icon-512.png` |
| Gráfico de funciones (feature graphic) | 1024×500 | Listo — `mobile/store/feature-graphic-1024x500.png` |
| Capturas de pantalla de teléfono | mínimo 2, cualquier resolución entre 320px y 3840px por lado | **Pendiente** — hacen falta capturas reales de la app corriendo (ver `RUNBOOK.md`, paso de build preview) |

## Política de privacidad

URL para pegar en Play Console → *Política de privacidad*:

```
https://claude.ai/artifact/JZj3W4SowaDjS1SSgdkm9a
```

Esta es una página publicada y accesible, generada a partir de `mobile/store/privacy-policy.html` (que queda en el repo como fuente y respaldo). **Antes de publicar**, hay que completar el email de contacto real en la sección 11 de ese documento — quedó marcado como pendiente a propósito, en vez de inventar uno. Si se prefiere una URL propia del dominio de la empresa en vez de la de claude.ai, se puede alojar el mismo archivo `privacy-policy.html` en cualquier hosting estático (el mismo Railway donde corre el backend, por ejemplo) y usar esa URL en su lugar.

## Email de contacto del desarrollador (Play Console)

**Pendiente** — Play Console pide un email de contacto público del desarrollador además del de la política de privacidad. No se completa acá porque no hay uno provisto; usar el mismo que se cargue en la política de privacidad.
