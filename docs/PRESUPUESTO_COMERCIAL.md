# PRESUPUESTO — SISTEMA DE GESTIÓN DE NEUMÁTICOS

*Documento comercial. Basado en el relevamiento técnico realizado sobre el sistema actual. Todos los importes marcados como "A DEFINIR" quedan pendientes de una tarifa/hora que el prestador debe indicar — no se inventó ningún valor económico.*

---

## 1. Descripción del proyecto

Se propone completar y consolidar el sistema de gestión de neumáticos ya existente (plataforma Web + aplicación móvil), enfocándose en cinco áreas de uso diario: el control de neumáticos y su ubicación, las órdenes de trabajo con talleres externos, el proceso de recapado, el control de stock y los inventarios físicos por depósito.

El sistema de base ya funciona y está en uso. Este trabajo consiste en corregir puntos donde una misma operación de negocio (por ejemplo, "un neumático fue recapado") puede registrarse de dos formas distintas dentro del sistema, completar controles de calidad que hoy faltan, y — según lo que la empresa decida — ampliar la aplicación móvil y definir si se necesita operar por cantidad además de por neumático individual.

## 2. Objetivo

Que la empresa cuente con un sistema único y confiable para:

- saber en todo momento dónde está cada neumático y en qué condición;
- registrar movimientos de stock sin errores ni duplicaciones;
- gestionar órdenes de trabajo con gomerías/recapadoras de forma prolija y trazable;
- controlar el proceso de recapado de punta a punta, sin ambigüedad sobre cómo quedó registrado cada caso;
- realizar inventarios físicos por depósito y detectar diferencias;
- operar todo lo anterior tanto desde una computadora (Web) como desde el celular (App), con la misma información y las mismas reglas en ambas.

## 3. Alcance

### Módulo 1 — Gestión de neumáticos

- Alta de neumáticos (por compra o por carga manual de stock inicial).
- Modificación de datos y baja definitiva.
- Identificación individual de cada neumático (número interno, código de fábrica, código QR).
- Estados del neumático (nuevo, usado, recapado, reparado, etc.) y su ubicación actual.
- Historial completo de cada neumático.

**Este módulo ya existe y funciona correctamente.** No se detectaron correcciones obligatorias.

### Módulo 2 — Stock

- Disponibilidad de stock por depósito.
- Seguimiento individual de cada neumático (no hay "cantidad" sin identidad — cada unidad física es un registro propio, lo cual da trazabilidad total).
- Movimientos de stock y su historial, que no puede alterarse una vez registrado.
- Trazabilidad de todo movimiento: quién, cuándo y por qué.

**Este módulo ya existe y es uno de los puntos más sólidos del sistema actual.** Si la empresa necesita además operar "por cantidad" (por ejemplo, enviar a recapar un número de neumáticos de una medida sin elegir cuáles) eso es una funcionalidad nueva a definir — ver sección "Decisiones pendientes".

### Módulo 3 — Órdenes de Trabajo

- Creación de órdenes de trabajo para talleres externos (gomerías/recapadoras).
- Estados de la orden (abierta, en taller, cerrada, cancelada).
- Neumáticos involucrados en cada orden (uno o varios).
- Costos asociados y cierre con historial.

**Este módulo ya existe para el circuito de taller externo.** Hoy no incluye el vínculo con la unidad/vehículo (montaje, desmontaje y rotación se manejan con otra funcionalidad del sistema, ya existente, pero separada). Unificar ambas cosas en un único concepto de "Orden de Trabajo" es una decisión de alcance pendiente de confirmar con la empresa (ver "Decisiones pendientes").

### Módulo 4 — Recapado

- Envío de neumáticos a recapado.
- Estado "en recapado".
- Recepción del taller.
- Retorno a stock.
- Trazabilidad del proceso.

**Este es el módulo con más trabajo pendiente.** Se detectó que hoy existen dos formas distintas de registrar que un neumático fue recapado, lo que puede generar reportes inconsistentes. Se recomienda unificar este proceso. Además, hoy no existe un paso de "rechazo" (cuando el taller no puede recapar la cubierta) ni la posibilidad de enviar una cantidad de neumáticos a recapar sin elegir cada uno manualmente — ambas son funcionalidades a definir con la empresa antes de presupuestarlas en firme.

### Módulo 5 — Inventario

- Consulta de stock en tiempo real.
- Inventario físico por depósito: conteo, comparación contra lo esperado, diferencias (faltantes/sobrantes), y cierre con ajuste opcional.
- Registro de auditoría de cada inventario realizado.

**Este módulo ya existe, está probado y funciona correctamente.** No se detectaron correcciones obligatorias.

### Módulo 6 — Web

El sistema Web ya cubre la operación administrativa completa: alta y gestión de neumáticos, stock, órdenes de trabajo e inventarios, con formularios claros y sin partes pendientes de desarrollo detectadas en este relevamiento.

### Módulo 7 — App

La aplicación móvil permite operar en campo: consultar stock, registrar movimientos, y — recientemente incorporado — gestionar órdenes de trabajo e inventarios físicos desde el celular. Esta última parte fue desarrollada hace poco y todavía necesita ser validada en uso real (con el equipo de campo, en dispositivos reales) antes de darla por terminada. Se prioriza que la app sea rápida y simple de usar: búsqueda ágil, lectura de código QR, confirmaciones claras y las operaciones más frecuentes a mano.

### Módulo 8 — Seguridad y permisos

El sistema ya cuenta con usuarios, roles y permisos diferenciados según el tipo de operación (consulta, carga, operaciones críticas como bajas o recapados). Se detectó un punto menor a prolijar: alinear los permisos de cierre/cancelación de órdenes de recapado con los permisos de apertura, para que sean exactamente consistentes.

### Módulo 9 — Auditoría y trazabilidad

El sistema registra quién hizo cada operación y cuándo. El historial de movimientos de stock está protegido: una vez registrado, no puede editarse ni borrarse. Se recomienda extender esa misma protección al registro general de auditoría, que hoy no la tiene.

### Módulo 10 — Testing y puesta en producción

El sistema cuenta con pruebas automáticas para la mayoría de los procesos críticos (stock, movimientos, inventario físico). Se detectó que el circuito de cierre de una orden de recapado no tiene ninguna prueba automática todavía — se recomienda agregarla antes de tocar ese circuito, para evitar romperlo sin darse cuenta. La puesta en producción de los cambios se realiza sobre la infraestructura ya existente (no requiere infraestructura nueva).

---

## 4. Estado actual vs. alcance del proyecto

| Funcionalidad | Estado actual | Trabajo necesario | Incluido |
|---|---|---|---|
| Neumáticos | EXISTENTE | Ninguno obligatorio | Sí (sin cambios) |
| Stock | EXISTENTE | Ninguno obligatorio, salvo si se define operar "por cantidad" | Sí (ampliación sujeta a decisión) |
| Movimientos | EXISTENTE | Ninguno | Sí (sin cambios) |
| Órdenes de Trabajo | PARCIAL | Definir si se unifica con la operación de vehículos; mejorar el vínculo con el historial | Sí, alcance sujeto a decisión |
| Recapado | A CORREGIR | Unificar los dos registros existentes; definir circuito de rechazo y de retorno a stock | Sí |
| Inventario | EXISTENTE | Ninguno obligatorio | Sí (sin cambios) |
| Web | EXISTENTE | Ninguno obligatorio | Sí (sin cambios) |
| App | PARCIAL | Validación en uso real de lo ya desarrollado; ajustes según resultado de esa validación | Sí |
| Auditoría | PARCIAL | Proteger el registro de auditoría contra edición/borrado; prolijar permisos | Sí |
| Tests | PARCIAL | Agregar prueba automática faltante en cierre de recapado; sumar pruebas de regresión sobre lo que se modifique | Sí |

---

## 5. Desglose por etapas

*Los rangos de horas son estimaciones basadas en la complejidad real detectada durante el relevamiento (cantidad de decisiones pendientes, módulos afectados y nivel de pruebas requerido). No se dispone de una tarifa horaria de referencia, por lo que los importes se dejan como `A DEFINIR`; una vez indicada la tarifa, cada importe surge de multiplicarla por las horas que finalmente se acuerden.*

| Etapa | Descripción | Complejidad | Estimación (horas) | Importe |
|---|---|---|---:|---:|
| 1 | Análisis y confirmación del modelo (definir las decisiones pendientes junto con la empresa) | Media | 8 – 16 | A DEFINIR |
| 2 | Gestión de neumáticos | Baja (sin cambios obligatorios) | 0 – 4 | A DEFINIR |
| 3 | Stock y movimientos | Baja si no cambia el modelo / Alta si se agrega operación "por cantidad" | 0 – 40 (según decisión) | A DEFINIR |
| 4 | Órdenes de Trabajo | Media si se mantiene como está mejorando trazabilidad / Alta si se unifica con la operación de vehículos | 16 – 100 (según decisión) | A DEFINIR |
| 5 | Recapado | Media | 16 – 40 | A DEFINIR |
| 6 | Inventario | Baja (sin cambios obligatorios) | 0 – 4 | A DEFINIR |
| 7 | Web | Baja (sin cambios obligatorios) | 0 – 8 | A DEFINIR |
| 8 | App | Media | 16 – 32 | A DEFINIR |
| 9 | Auditoría y permisos | Baja/Media | 8 – 16 | A DEFINIR |
| 10 | Testing | Media | 16 – 24 | A DEFINIR |
| 11 | Puesta en producción | Baja/Media | 4 – 8 | A DEFINIR |

---

## 6. Estimación de horas

| Etapa | Mínimo | Máximo | Motivo de variación |
|---|---:|---:|---|
| 1. Análisis y modelo | 8 | 16 | Depende de cuántas reuniones/idas y vueltas se necesiten para cerrar las decisiones pendientes |
| 3. Stock y movimientos | 0 | 40 | Si la empresa no necesita operar "por cantidad", este ítem casi no requiere trabajo; si sí lo necesita, es un cambio de fondo |
| 4. Órdenes de Trabajo | 16 | 100 | La diferencia depende enteramente de si se decide unificar la Orden de Trabajo con la operación sobre vehículos, que es un cambio bastante más profundo |
| 5. Recapado | 16 | 40 | Depende de si además de unificar el registro se agrega el circuito de rechazo y el envío por cantidad |
| 8. App | 16 | 32 | Depende de cuántos ajustes surjan al validar en uso real lo ya desarrollado |
| 10. Testing | 16 | 24 | Depende de cuántos módulos terminen modificándose |

La estimación contempla desarrollo, integración entre Web y App, pruebas, corrección de errores encontrados durante las pruebas, validación con la empresa y despliegue — no solamente tiempo de programación.

---

## 7. Modalidad de contratación

Se presentan tres alternativas. Ninguna se recomienda por sobre las otras — la elección depende de cómo la empresa prefiera gestionar el proyecto:

**Opción A — Proyecto cerrado**
Un precio total fijo por todo el alcance definido. Da previsibilidad total de costo, pero requiere cerrar el alcance exacto (incluidas las decisiones pendientes) antes de empezar.

**Opción B — Desarrollo por etapas**
Se paga cada módulo/etapa a medida que se entrega. Permite empezar por lo más urgente (por ejemplo, corregir el recapado) sin definir todavía el resto, y ajustar el alcance de los módulos siguientes con lo aprendido en los anteriores.

**Opción C — Bolsa de horas**
Un paquete de horas a utilizar según necesidad, sin atarlo a un alcance cerrado de antemano. Es útil si la empresa espera que vayan surgiendo pedidos adicionales durante el proyecto, pero da menos previsibilidad de costo total.

---

## 8. Forma de pago

```text
Forma de pago:
- [XX]% al iniciar el proyecto.
- [XX]% contra entrega de la etapa [X].
- [XX]% contra puesta en producción.
```

*(Valores a definir junto con la empresa — no se fijó ninguna condición comercial de antemano.)*

---

## 9. Plazo estimado

```text
Estimación de desarrollo:
Alcance mínimo (sin unificar Órdenes de Trabajo ni agregar operación por cantidad):
  aproximadamente 3 a 5 semanas

Alcance extendido (incluyendo las decisiones pendientes resueltas a favor del cambio):
  aproximadamente 7 a 12 semanas
```

*(Calculado sobre el rango de horas de la sección 6; el plazo real en semanas depende de cuántas horas semanales se asignen al proyecto — los números de arriba asumen una dedicación part-time habitual para un proyecto de este tamaño.)*

Factores que pueden modificar el plazo:
- cambios de alcance durante el desarrollo;
- demora en resolver las decisiones pendientes;
- integraciones adicionales no contempladas hoy;
- disponibilidad de la empresa para validar y dar feedback;
- calidad y volumen de los datos existentes a migrar/conciliar;
- inconvenientes heredados del sistema actual que aparezcan al profundizar.

---

## 10. Entregables

- Sistema Web con las correcciones y mejoras acordadas.
- Aplicación móvil validada y ajustada (órdenes de trabajo e inventario ya incorporados).
- Proceso de recapado unificado y sin duplicación de registros.
- Registro de auditoría protegido contra alteraciones.
- Pruebas automáticas agregadas sobre los puntos críticos corregidos.
- Documento de reglas de negocio actualizado y validado con la empresa.
- Sistema desplegado en producción.

*(Solo se incluyen los entregables realmente contemplados en el alcance de la sección 3. Cualquier otro pedido se evalúa aparte.)*

---

## 11. Fuera de alcance

- Funcionalidades no analizadas en este relevamiento (por ejemplo, un eventual módulo de venta de neumáticos, que hoy no existe en el sistema y no fue solicitado).
- Integraciones con sistemas externos (contables, ERP, GPS, etc.).
- Compra o provisión de hardware (lectores de código, dispositivos móviles).
- Infraestructura y hosting (el sistema continúa sobre la infraestructura ya utilizada actualmente).
- Servicios de terceros.
- Mantenimiento indefinido o soporte permanente (ver sección 13).

---

## 12. Supuestos

- El sistema actual es la base sobre la que se trabaja; no se reconstruye desde cero.
- La infraestructura y los servicios ya utilizados hoy continúan disponibles sin cambios.
- Las funcionalidades ya existentes (gestión de neumáticos, stock, movimientos, inventario físico, Web) se reutilizan tal cual están, salvo los ajustes puntuales indicados.
- No se contemplan integraciones externas adicionales a las ya existentes.
- La empresa participa en la resolución de las decisiones pendientes (sección "Decisiones que deben confirmarse") antes de comenzar el desarrollo de los módulos que dependen de ellas.

---

## 13. Garantía / correcciones

```text
Período de garantía por errores:
[XX] días

Incluye:
- Corrección de errores derivados del alcance contratado.

No incluye:
- Nuevas funcionalidades.
- Cambios de alcance.
- Modificaciones solicitadas con posterioridad a la entrega.
```

---

## 14. Mantenimiento posterior

**Desarrollo inicial:** lo detallado en este presupuesto, con su garantía correspondiente (sección 13).

**Mantenimiento posterior** (no incluido en el precio del desarrollo inicial, a contratar por separado si se requiere): corrección de errores fuera del período de garantía, actualizaciones, mejoras incrementales, nuevas funcionalidades y soporte continuo.

---

## 15. Resumen económico

| Concepto | Importe |
|---|---:|
| Desarrollo | A DEFINIR |
| Testing | A DEFINIR |
| Puesta en producción | A DEFINIR |
| Otros | A DEFINIR |
| **TOTAL** | **A DEFINIR** |

*No se cuenta con una tarifa horaria de referencia; por eso no se fija ningún importe. Con una tarifa horaria definida, el total surge directamente de aplicarla a los rangos de horas de la sección 6, según el alcance y la modalidad de contratación que se elijan.*
