# DECISIONES PENDIENTES

*Estas decisiones deben confirmarse con la empresa antes de cerrar definitivamente el alcance y el precio. Ninguna de ellas se resolvió por criterio propio: donde el repositorio no da una respuesta clara, se deja así de forma explícita, en lugar de asumir algo.*

## DECISIÓN 1 — Cómo debe quedar registrado un recapado

**Situación:** hoy existen dos formas de registrar que un neumático fue recapado, según el camino que se use dentro del sistema (una queda ligada al taller y al costo, la otra no).

**Opciones:**
- **A.** Mantener ambas formas, pero mejorarlas para que las dos dejen exactamente el mismo tipo de registro.
- **B.** Eliminar una de las dos formas y que todo recapado pase obligatoriamente por el circuito con taller.
- **C.** Dejarlo como está y documentar que son dos flujos intencionalmente distintos (uno formal con taller, otro de corrección rápida).

**Impacto:** la opción B elimina una función que hoy se usa — no se recomienda sin que la empresa lo pida expresamente. La opción A es la de menor riesgo.

## DECISIÓN 2 — ¿Hace falta operar "por cantidad" en vez de por neumático individual?

**Situación:** hoy, para enviar neumáticos a recapar (o para cualquier otra operación), siempre hay que elegir cada neumático puntual. No existe la opción de decir "mandá 20 neumáticos de tal medida" sin elegir cuáles.

**Opciones:**
- **A.** No hace falta — se mejora la pantalla para que elegir varios neumáticos de la misma medida sea rápido, pero se sigue eligiendo cada uno.
- **B.** Sí hace falta — se agrega la posibilidad de operar por cantidad.

**Impacto:** la opción B es un cambio de fondo en cómo funciona el sistema hoy (que siempre identifica cada neumático individualmente, algo que da trazabilidad completa). Es la decisión que más cambia el presupuesto si se elige.

## DECISIÓN 3 — ¿Se unifica la Orden de Trabajo con la operación sobre vehículos?

**Situación:** hoy "Orden de Trabajo" (taller externo) y la operación de montar/desmontar/rotar neumáticos en un vehículo son dos funcionalidades separadas dentro del sistema.

**Opciones:**
- **A.** Unificar todo bajo un único concepto de "Orden de Trabajo".
- **B.** Mantenerlas separadas, pero mejorar cómo se relacionan entre sí.
- **C.** Dejarlas como están, solo aclarando la terminología para evitar confusiones.

**Impacto:** la opción A es la de mayor esfuerzo de todo el proyecto, porque toca una parte del sistema que hoy funciona bien y está probada. Se recomienda no encararla salvo que la empresa confirme que realmente necesita ver todo bajo una sola pantalla/reporte.

## DECISIÓN 4 — ¿Qué pasa exactamente cuando se cierra una orden de recapado?

**Situación:** hoy, al cerrar una orden de recapado con taller, el neumático queda marcado como recapado pero no vuelve automáticamente a stock disponible (a diferencia de una reparación, que sí vuelve).

**Opciones:**
- **A.** Es un error — debería volver a stock automáticamente, igual que una reparación.
- **B.** Es intencional — se quiere un paso extra de inspección manual antes de que el neumático vuelva a estar disponible.

**Impacto:** si es A y no se corrige, puede haber neumáticos recapados que "se pierden" operativamente porque nadie los pasa a stock a mano. Si es B, no hay que corregir nada, pero conviene que ese paso de inspección sea una acción explícita y visible en el sistema, no algo implícito.

---

Estas cuatro decisiones determinan directamente los rangos de horas de las etapas 3, 4 y 5 del presupuesto (`PRESUPUESTO_COMERCIAL.md`, sección 5).
