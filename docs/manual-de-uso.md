# Manual de uso — Trazabilidad de cubiertas

Sistema para flotas de camiones, semirremolques, tanques y bateas. El historial es de **cada cubierta** (por ejemplo `FH:01 Nº30363`), no de la patente. Tractor + acoplado es solo la vista de trabajo.

Este manual describe el producto tal como está hoy. La pantalla **Ayuda** muestra qué puede hacer tu usuario según el rol.

---

## 1. Entrar

1. Abrí la dirección del sistema (en la demo local: `http://localhost:8093`).
2. Ingresá usuario y contraseña.
3. El menú de la izquierda agrupa **Operación**, **Consulta** y, si sos administrador, **Catálogo**.
4. Arriba a la derecha ves tu nombre y el rol. **Salir** cierra la sesión.

### Tamaño de letra

En la barra superior, **Letra** cambia el tamaño: `A` normal, `A+` grande, `A++` extra grande. El valor queda guardado en el navegador.

### Buscar una cubierta

El buscador de la barra superior busca por número o texto en **Neumáticos**.

---

## 2. Roles

| Rol | Para qué está |
|---|---|
| **Consulta** | Ver tablero, planillas, stock, fichas y reportes. No opera. |
| **Operario** | Planilla del día: montar, cambiar, rotar, medir, pinchadura, retirar a stock. Compras. |
| **Logística** | Lo del operario, más acoplar/desacoplar y corregir odómetros. |
| **Jefe de sector** | Lo de logística, más baja, recapado y cambio de configuración de ejes. |
| **Administrador** | Todo lo anterior, más catálogos, usuarios y edición de datos maestros. |

La matriz completa está en **Consulta → Ayuda**.

---

## 3. Tablero

Es la pantalla de inicio. Las tarjetas grandes muestran cantidades:

- Total de cubiertas
- En stock
- Instaladas
- En reserva
- Auxilio
- En reparación
- De baja
- Kilómetros acumulados

Tocá una tarjeta para ir al listado filtrado. Más abajo hay accesos a Unidades, Stock, Nueva compra, Odómetros y Ayuda, más tablas de marcas, próximas a baja, últimas lecturas e incidencias por patente.

---

## 4. Unidades y planilla

**Operación → Unidades** lista patentes, tipo, configuración, flota, acoplado y odómetro.

- Filtrá por patente o flota.
- Tocá la patente para abrir la **planilla**.
- **Nueva unidad** da de alta tractor, semi, tanque o batea (roles con escritura).
- Solo el **administrador** ve **Editar** sobre una unidad ya creada.

### Qué ves en la planilla

- Encabezado con patentes (tractor + acoplado si está unido), tipo, configuración, flota, base y fecha.
- Campo **Odómetro**: el km del tractor. En un acoplado sin tractor el sistema no deja registrar km.
- Mapa del chasis, de frente hacia atrás, con ubicaciones numeradas. El **auxilio** no es una rueda de rodaje: no suma kilómetros.
- Si tu rol opera: riel izquierdo con cubiertas de **stock** (se pueden arrastrar) y esquemas de **rotación**.
- Panel derecho **Ubicación**: ficha de la cubierta tocada y acciones.

### Operar una ubicación (operario o superior)

1. Cargá el odómetro actual del tractor **antes** de confirmar. Ese km se asienta al guardar.
2. Tocá una ubicación del mapa (o arrastrá una cubierta de stock a un hueco vacío).
3. En el panel derecho elegí la acción:

| Acción | Qué hace |
|---|---|
| **Instalar** | Pone una cubierta de stock compatible en una ubicación vacía. |
| **Cambio** | Retira la cubierta actual (vuelve a stock) e instala otra. |
| **Pinchadura** | Registra el evento y manda la cubierta a **reparación**. La ubicación queda libre. |
| **Rotación** | Intercambia con otra ubicación. **No cierra** el periodo de km. |
| **Retirar** | Saca la cubierta. Todo retiro pasa por **stock**, aunque sea de pasaje. |
| **Incidencia** | Carga un evento sobre esa cubierta (corte, globo, etc.). |
| **Medición** | Guarda profundidades en milímetros por zona de banda. |

También podés arrastrar cubiertas entre ubicaciones o usar los botones de esquema (longitudinal, cruzado, diagonal) cuando el mapa está completo.

### Compatibilidad

El sistema no deja montar una medida que no entra en esa ubicación. En acoplados lineales la unidad tiene **295** o **385** (gomón). Eso se guarda en **Datos** de la planilla.

### Acoplar y desacoplar (logística, jefe, administrador)

En la planilla, panel **Acoplar / desacoplar**:

1. Elegí la otra unidad (semi/tanque/batea si estás en el tractor, o el tractor si estás en el acoplado).
2. Indicá el km del tractor.
3. **Acoplar**. Si ya había un conjunto, se cierra el anterior.
4. **Desacoplar** cierra los tramos de km del acoplado.

Un tanque, semi o batea **sin acoplar no opera kilómetros**. Primero el acople.

### Cambio de configuración (jefe, administrador)

Pasa la unidad de un layout a otro (por ejemplo 6X4). Hay que indicar el motivo. **Las cubiertas instaladas vuelven a stock** porque las ubicaciones cambian.

---

## 5. Stock

**Operación → Stock**: cubiertas disponibles para instalar. Desde acá se entra a la ficha. El stock es el lugar de paso de todo retiro.

Estados que vas a ver en el sistema:

| Estado | Significado |
|---|---|
| Stock | Lista para montar |
| Instalada | En una ubicación de rodaje |
| Reserva | Apartada |
| Auxilio | Montada como auxilio (no suma km) |
| En reparación | Salida por pinchadura u otro arreglo |
| De baja | Fuera de servicio |

---

## 6. Ficha de la cubierta

**Operación → Neumáticos** o el buscador superior. Cada cubierta se identifica por marca, diseño y número.

La ficha muestra estado, condición, vida actual, km acumulados, profundidad mínima y ubicación. Más abajo, historial de movimientos, incidencias y mediciones.

- **Incidencia** y **medición** se pueden cargar también desde acá (roles con escritura).
- **Baja** (jefe o administrador): saca la cubierta de circulación con un motivo.
- **Recapado** (jefe o administrador): hay que retirarla de la unidad antes. Cierra la vida actual y abre otra. Una **reparación no abre vida nueva**.
- **Editar** datos maestros: solo administrador. Si la cubierta ya tiene historial, no se borra: se desactiva.

---

## 7. Compras

**Operación → Compras**.

1. **Nueva compra**: proveedor, base, fecha.
2. En cada línea: marca → diseño de esa marca → medida en la que se fabrica → cantidad → número inicial.
3. **Crear borrador**. Los números se generan consecutivos (ejemplo: desde 30363, cantidad 4 → 30363 a 30366).
4. Revisá la compra y **Confirmar**. Recién ahí las cubiertas entran a **stock**.

El administrador puede editar un borrador o descartarlo. Una compra confirmada con cubiertas en uso no se elimina.

---

## 8. Odómetros

**Operación → Odómetros**.

El km se registra cuando operás la planilla (montar, cambio, acople, etc.). Si el valor es el mismo que el último, no se duplica la fila. El sistema no acepta un km **menor** al anterior de esa unidad.

**Logística, jefe y administrador** pueden **Editar** una lectura mal cargada. Consulta y operario solo ven el listado.

El acoplado no tiene reloj propio: los km de sus cubiertas salen del tractor acoplado en ese tramo.

---

## 9. Reportes (Consulta)

| Pantalla | Para qué |
|---|---|
| **Km por cubierta** | Acumulado, vidas, recapados y reparaciones. |
| **Consumo** | Rendimiento y desgaste para decidir rotación, recap o baja. |
| **Incidencias** | Eventos (pinchadura, corte, recapado, etc.). |
| **Movimientos** | Auditoría en castellano: quién montó, acopló, midió o dio de baja. |

Los eventos históricos **no se reescriben**. Si hay un error de km, se corrige la lectura de odómetro; el rastro queda.

---

## 10. Catálogo y usuarios (solo administrador)

El grupo **Catálogo** aparece únicamente al administrador.

| Pantalla | Uso |
|---|---|
| Marcas, modelos, medidas | Producto de cubierta. El modelo pertenece a una marca. |
| Flotas y bases | Organización de la empresa. |
| Proveedores | Compras. |
| Tipos y motivos | Tipos de unidad, motivos de baja/movimiento, configuraciones de ejes. |
| Usuarios | Nombre, usuario, rol, flotas y bases. |

Cómo se edita: el listado es de lectura. Arriba hay **un** formulario. **Editar** carga ese registro. **Eliminar** borra solo si no tiene historial; si lo tiene, se **desactiva**.

---

## 11. Flujo típico de un día

1. Logística acopla el tanque al tractor e indica el km.
2. El operario abre la planilla, carga el odómetro y completa cubiertas vacías desde stock (o arrastra).
3. Si hay desgaste irregular, mide y rota (el periodo de km sigue abierto).
4. Si pinhca: **Pinchadura** → la cubierta va a reparación y la ubicación queda libre → se instala otra de stock o el auxilio.
5. Al cortar el viaje, si cambia el conjunto: desacoplar con el km actual.
6. Jefe revisa **próximas a baja** en el tablero y, si corresponde, da de baja o manda a recapar.
7. Gerencia mira **Km por cubierta** y **Movimientos** sin cargar nada (rol Consulta).

---

## 12. Reglas que el sistema no negocia

- Una cubierta, una ubicación actual.
- Todo retiro pasa por stock, aunque sea de pasaje.
- Recapado abre vida nueva. Reparación no.
- El auxilio no suma km.
- La rotación no cierra el periodo de km.
- Los eventos históricos son inmutables.
- Semi, tanque o batea sin tractor acoplado no registran km.

---

## 13. Si algo no se puede guardar

El sistema muestra un mensaje arriba de la pantalla. Causas frecuentes:

- Km menor al último asentado.
- Cubierta ya instalada en otra unidad.
- Medida incompatible con la ubicación (295 vs 385, o diseño que no entra).
- Acoplado sin tractor al intentar operar km.
- Recapado o baja sin el rol de jefe o administrador.
- Intentar borrar un catálogo que ya se usó (hay que desactivarlo).

Si el mensaje no alcanza, pedile a un administrador que revise **Movimientos**: ahí queda quién hizo cada acción.
