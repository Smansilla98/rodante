# RESUMEN EJECUTIVO — Uso interno

*Para toma de decisiones internas antes de cerrar alcance y presupuesto con el cliente. No es el documento a compartir con el cliente (ver `PRESUPUESTO_COMERCIAL.md` para eso). El detalle técnico completo está en `AUDIT_OT_STOCK_RECAPADO.md`.*

## Qué se encontró

El sistema tiene una base sólida: la identificación individual de neumáticos, el historial de movimientos (inmutable, no se puede alterar) y el inventario físico por depósito están bien resueltos y probados. No hace falta rehacer nada de eso.

El problema real está concentrado en **recapado** y, en menor medida, en el concepto de **Orden de Trabajo**:

1. Hay dos formas distintas de registrar en el sistema que un neumático fue recapado, según por dónde se haga la operación. Ambas funcionan, pero no están unificadas — esto puede hacer que un reporte de "cuántos recapados tuvimos" dé un número incompleto según dónde se consulte.
2. El circuito que más se usaría en la práctica para recapar (con taller y costo asociado) es justo el que menos pruebas automáticas tiene — si se toca sin cuidado, se puede romper sin que nadie se entere hasta que falle en producción.
3. "Orden de Trabajo" hoy es en realidad dos cosas separadas en el sistema: una para taller externo (gomería/recapadora) y otra para operaciones sobre el vehículo (montar/desmontar/rotar neumáticos). Ninguna de las dos por sí sola es lo que normalmente se entendería como "una OT completa".
4. No existe la posibilidad de enviar a recapar "una cantidad" de neumáticos sin elegir cada uno — todo el sistema trabaja por neumático individual, lo cual es correcto para la trazabilidad pero no cubre ese caso de uso si la empresa lo necesita.

Nada de esto es urgente en el sentido de "el sistema está fallando hoy" — es una cuestión de consistencia de datos y de cobertura de casos, que conviene resolver antes de seguir construyendo sobre esa base.

## Qué falta decidir antes de cerrar precio

Ver `DECISIONES_PENDIENTES.md` para el detalle. En síntesis, hay 4 decisiones de negocio (no técnicas) que cambian bastante el tamaño del trabajo:

- si conviene unificar los dos registros de recapado o dejarlos como dos flujos distintos a propósito;
- si el negocio realmente necesita operar "por cantidad" en vez de elegir cada neumático;
- si conviene unificar el concepto de "Orden de Trabajo" con la operación sobre vehículos, o dejarlos separados como están;
- qué debe pasar exactamente cuando se cierra una orden de recapado (si el neumático vuelve solo a stock o si hay un paso de inspección manual antes).

## Recomendación

Cerrar estas 4 decisiones con el cliente antes de presupuestar en firme el alcance completo. Mientras tanto, se puede presupuestar y arrancar con lo que **no depende de ninguna decisión**: unificar la trazabilidad del recapado sin cambiar el modelo de datos, agregar la prueba automática que falta, y proteger el registro de auditoría — eso ya solo cubre los hallazgos más críticos detectados.

## Estado de preparación

**NO LISTO PARA IMPLEMENTAR** en el alcance completo, tal como está definido hoy. **SÍ LISTO** para arrancar por el subconjunto de correcciones que no dependen de las decisiones pendientes.
