# Propuesta económica — Trazabilidad de cubiertas

**Para:** [Nombre de la empresa de transportes]  
**De:** [Razón social del proveedor]  
**Fecha:** 14 de agosto de 2026  
**Validez de la oferta:** 30 días  
**Moneda de referencia:** USD (facturación en ARS al tipo de cambio acordado el día de emisión de factura)

Este documento sirve para cotizar la **puesta en marcha** del sistema de trazabilidad individual de cubiertas ya desarrollado, no un desarrollo desde cero. Los importes son una **propuesta de trabajo** para una flota de transporte de cargas: ajustarlos según cantidad de unidades, bases y usuarios.

---

## 1. Qué se está cotizando

Software web para controlar **cada cubierta** de la flota (tractor, semirremolque, tanque, batea) con número propio, historial de km, rotaciones, pinchaduras, recapados y bajas.

El dato vive en la cubierta, no en la patente. El acoplado usa el odómetro del tractor mientras está unido.

**Alcance de producto (versión actual):**

- Planilla visual del chasis (ubicaciones numeradas, auxilio, rotación)
- Stock, compras por número consecutivo, ficha de cubierta
- Acoples tractor + semi/tanque/batea
- Odómetros, mediciones de profundidad, incidencias
- Reportes de km, consumo, incidencias y movimientos (auditoría)
- Roles: Consulta, Operario, Logística, Jefe de sector, Administrador
- Catálogos y usuarios (administrador)
- Manual de uso y ayuda en pantalla según rol

**Fuera de esta versión (no incluidos, se cotizan aparte si se piden):**

- Módulo de costos / costo por km de cubierta
- App nativa para celular
- Integración con GPS, ERP o taller externo
- Facturación de proveedores o stock valorado
- Configuraciones de ejes no parametrizadas hoy (por ejemplo 7X24)

---

## 2. Hipótesis de flota (completar)

Usar estos números para cerrar el precio. Si la flota es otra, se recalcula la implantación (ítem B), no la licencia base.

| Dato | Valor propuesto | Valor del cliente |
|---|---|---|
| Tractores | 25 | |
| Acoplados (semi / tanque / batea) | 30 | |
| Cubiertas activas (aprox.) | 600 | |
| Bases operativas | 2 | |
| Usuarios concurrentes | 15 | |
| Modalidad | Nube del proveedor **o** servidor del cliente | |

---

## 3. Rubros

### A. Licencia de uso del sistema

Derecho de uso del producto para una empresa, un entorno productivo y un entorno de prueba. Incluye actualizaciones menores de corrección durante el primer año de soporte (ítem D).

### B. Implantación y parametrización

Trabajo único para dejar el sistema listo en la operación real:

1. Relevamiento de flota, bases, marcas que compran y reglas de taller
2. Alta de catálogos (marcas, diseños, medidas, motivos de baja)
3. Carga de unidades y configuraciones de ejes
4. Usuarios y roles
5. Carga inicial de cubiertas en servicio (planilla de arranque) o importación desde planilla
6. Prueba con un tractor + acoplado piloto antes del resto de la flota

### C. Capacitación

Tres sesiones presenciales o por videollamada, con el manual de uso del producto:

| Sesión | Destinatarios | Duración |
|---|---|---|
| 1. Planilla y stock | Operarios y logística | 3 h |
| 2. Acoples, odómetros, baja y recap | Logística y jefes de sector | 2 h |
| 3. Tablero, reportes y catálogo | Jefes, administración, gerencia (rol Consulta) | 2 h |

Queda una sesión de refuerzo de 2 h dentro de los 30 días posteriores a la salida en vivo.

### D. Hosting, resguardo y soporte (mensual, a partir de la salida en vivo)

- Hosting, copias de resguardo diarias y certificado HTTPS (si la modalidad es nube)
- Mesa de ayuda en días hábiles, horario a acordar (propuesta: 9 a 18, GMT-3)
- Hasta 8 horas/mes de correcciones y dudas de uso
- Horas extra de cambio funcional: ítem E

Si el cliente hospeda en su servidor, se descuenta el hosting y queda soporte + actualizaciones.

### E. Evolutivos (opcional, bajo pedido)

Cambios de producto no incluidos en el alcance actual (costos, integración, app, nuevos layouts). Se cotizan por hora o por hito.

---

## 4. Presupuesto

Montos en USD, sin IVA. Completar la columna **Oferta** al cerrar con el cliente. La columna **Rango de referencia** es una guía de mercado para un producto de este porte, no un precio cerrado.

| Ítem | Unidad | Cant. | Rango de referencia (USD) | Oferta (USD) |
|---|---|---|---|---|
| A. Licencia de uso — primer año | Año | 1 | 4.800 – 7.200 | |
| A. Licencia de uso — años siguientes | Año | 1 | 2.400 – 3.600 | |
| B. Implantación y parametrización (hipótesis §2) | Proyecto | 1 | 3.500 – 6.500 | |
| B. Carga inicial extra (si supera 600 cubiertas) | Por 100 cubiertas | | 180 – 280 | |
| C. Capacitación (paquete de 3 sesiones + refuerzo) | Paquete | 1 | 900 – 1.400 | |
| C. Sesión extra / nueva base | Sesión 2 h | | 180 – 250 | |
| D. Hosting + soporte nube | Mes | 12 | 280 – 420 / mes | |
| D. Solo soporte (servidor del cliente) | Mes | 12 | 180 – 280 / mes | |
| E. Evolutivos | Hora | a demanda | 45 – 70 | |

### Totales de salida (llenar)

| Concepto | USD | ARS (indicativo) |
|---|---|---|
| Único (A primer año + B + C) | | |
| Recurrente año 1 (D × 12) | | |
| **Total primer año** | | |
| **Año 2 en adelante** (A renovación + D × 12) | | |

IVA: [21 % / según condición fiscal]. Viáticos de capacitación presencial: a cargo del cliente o se suman al ítem C.

---

## 5. Forma de pago (propuesta)

| Hito | Porcentaje |
|---|---|
| Aceptación de esta propuesta | 40 % del único (A+B+C) |
| Entorno piloto operativo (un conjunto tractor + acoplado cargado) | 40 % del único |
| Salida en vivo + actas de capacitación | 20 % del único |
| Ítem D | Mes adelantado, desde el pase a producción |

---

## 6. Plazo

| Etapa | Duración estimada |
|---|---|
| Kick-off y relevamiento | 1 semana |
| Parametrización y carga piloto | 2 semanas |
| Carga del resto de la flota + capacitación | 2 semanas |
| Acompañamiento post arranque | 2 semanas (incluido en D) |

**Salida en vivo orientativa:** 5 a 7 semanas desde la orden de compra, si el cliente entrega a tiempo el padrón de unidades, cubiertas y usuarios.

Dependencias del cliente: un referente de taller, un referente de sistemas (si hay servidor propio), acceso a las planillas actuales de cubiertas y un horario de playa para la sesión de operarios.

---

## 7. Condiciones

- El historial cargado a partir de la salida en vivo es el dato oficial. El pasado anterior a la carga inicial se registra como saldo de km / vida, no como cada movimiento histórico, salvo que se contrate digitalización extra.
- Los usuarios y roles los define el cliente. El proveedor no opera la flota.
- Copias de resguardo retenidas 30 días (nube). Exportación de datos al cierre de contrato: incluida una vez, en formato abierto (CSV/SQL).
- Horas de soporte no usadas no se acumulan al mes siguiente, salvo acuerdo escrito.
- Viajes al interior: pasajes y estadía a cargo del cliente o se cotizan aparte.

---

## 8. Cómo usar este archivo

1. Completar la tabla del §2 con la flota real.
2. Elegir nube o servidor propio (define el ítem D).
3. Cerrar la columna **Oferta** dentro del rango, o fuera si hay más bases / más cubiertas.
4. Reemplazar los corchetes del encabezado.
5. Enviar en PDF junto con el [manual de uso](manual-de-uso.md).

---

## 9. Aceptación

| | Cliente | Proveedor |
|---|---|---|
| Razón social | | |
| Nombre y cargo | | |
| Firma y fecha | | |
