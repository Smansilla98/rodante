# Manual de uso — Rodante

**Rodante** es la gestión inteligente de neumáticos para flotas de camiones, semirremolques, tanques y bateas. El historial es de **cada cubierta** (por ejemplo `FH:01 Nº30363`), no de la patente. Tractor + acoplado es solo la vista de trabajo.

Este manual describe el producto tal como está hoy. La pantalla **Ayuda** muestra qué puede hacer tu usuario según el rol.

---

## 1. Entrar

1. Abrí la dirección del sistema (en la demo local: `http://localhost:8093`).
2. Ingresá usuario y contraseña.
3. El menú de la izquierda está separado en grupos cortos para encontrar más fácil:
   - **Día a día**: tablero, buscar cubierta (nº o QR), unidades, odómetros.
   - **Cubiertas**: stock, neumáticos, compras, punto de partida, órdenes y (jefe/admin) dar de baja.
   - **Seguimiento**: mediciones, incidencias, enganches.
   - **Informes**: km, informe semanal, consumo, predictivo, inventarios.
   - **Costos**: costo/km, costo por unidad/posición y costos.
   - **Control**: movimientos, avisos, ayuda (y telemetría/integridad según rol).
   - **Catálogo** (solo administrador) y **Plataforma** (super admin).
4. Arriba a la derecha ves tu nombre y el rol. **Salir** cierra la sesión.

### Tamaño de letra

En la barra superior, **Letra** cambia el tamaño: `A` normal, `A+` grande, `A++` extra grande. El valor queda guardado en el navegador.

### Buscar una cubierta

El buscador de la barra superior busca por número o texto en **Neumáticos**. En **Día a día → Buscar cubierta** escribí el número o pegá / escaneá el QR: si hay una sola coincidencia entra directo a acciones grandes (ficha, planilla, devolver a stock). Pensado para playa o camión, desde el celular.

---

## 2. Roles

| Rol | Para qué está |
|---|---|
| **Consulta** | Ver tablero, planillas, stock, fichas y reportes. No opera. |
| **Operario** | Planilla del día: montar, cambiar, rotar, medir, pinchadura, retirar a stock. Compras. |
| **Logística** | Lo del operario, más acoplar/desacoplar y corregir odómetros. |
| **Jefe de sector** | Lo de logística, más baja, recapado y cambio de configuración de ejes. |
| **Administrador** | Todo lo anterior, más catálogos, usuarios y edición de datos maestros. |

La matriz completa está en **Control → Ayuda**.

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

Tocá una tarjeta para ir al listado filtrado. Más abajo hay colas (reparación, profundidad, baja, integridad) y accesos a Buscar cubierta, Unidades, Stock, Ingresar compra, Odómetros y Ayuda.

---

## 4. Unidades y planilla

**Día a día → Unidades** lista patentes, tipo, configuración, flota, acoplado y odómetro.

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

**Cubiertas → Stock**: cubiertas disponibles para instalar. Desde acá se entra a la ficha. El stock es el lugar de paso de todo retiro.

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

**Cubiertas → Neumáticos** o el buscador superior. Cada cubierta se identifica por marca, diseño y número.

La ficha muestra primero la **situación actual**, después **qué hacer ahora** (acciones en tarjetas: medición, incidencia, devolver a stock, traslado, baja) y el **historial** de más reciente a más antiguo.

- **Informe de vida**: documento completo para imprimir o guardar PDF (historial, vidas, mediciones, costos, pronóstico y fotos de baja).
- **Incidencia** y **medición** se pueden cargar también desde acá (roles con escritura).
- **Baja** (jefe o administrador): también se puede hacer desde la ficha, con motivo, notas y fotos (hasta 6). Para varias cubiertas a la vez usá el módulo **Dar de baja** (sección siguiente).
- **Recapado** (jefe o administrador): hay que retirarla de la unidad antes. Cierra la vida actual y abre otra. Una **reparación no abre vida nueva**.
- **Editar** datos maestros: solo administrador. Si la cubierta ya tiene historial, no se borra: se desactiva.

---

## 7. Dar de baja (módulo)

**Cubiertas → Dar de baja** (solo jefe de sector o administrador).

Sirve para dar de baja una o **varias** cubiertas juntas, sin abrir cada ficha.

1. Arriba aparecen las **listas para dar de baja**: están fuera de unidad (stock, reserva o reparación).
2. Marcá las que correspondan (el tilde de la cabecera marca todas de la página).
3. Elegí el **motivo**, opcionalmente una observación, y tocá **Dar de baja seleccionadas**.
4. Abajo aparecen las que **siguen en una unidad**: no se pueden dar de baja hasta retirarlas a stock desde la planilla (hay un botón directo a la planilla).

La baja es definitiva: no se reinstala. El historial se conserva. Si marcás alguna que todavía está montada, esa se saltea y el resto se da de baja igual.

---

## 8. Compras

**Cubiertas → Compras**.

1. **Ingresar compra** (o **Nueva compra** en el listado de compras): proveedor, base, fecha.
2. En cada línea: marca → diseño de esa marca → medida en la que se fabrica → cantidad → número inicial.
3. **Crear borrador**. Los números se generan consecutivos (ejemplo: desde 30363, cantidad 4 → 30363 a 30366).
4. Revisá la compra y **Confirmar**. Recién ahí las cubiertas entran a **stock**.

El administrador puede editar un borrador o descartarlo. Una compra confirmada con cubiertas en uso no se elimina.

No confundir con **Punto de partida**: la compra es ingreso comercial con proveedor; el punto de partida es stock previo sin OC.

---

## 9. Odómetros

**Día a día → Odómetros**.

El km se registra cuando operás la planilla (montar, cambio, acople, etc.). Si el valor es el mismo que el último, no se duplica la fila. El sistema no acepta un km **menor** al anterior de esa unidad.

**Logística, jefe y administrador** pueden **Editar** una lectura mal cargada. Consulta y operario solo ven el listado.

El acoplado no tiene reloj propio: los km de sus cubiertas salen del tractor acoplado en ese tramo.

---

## 10. Informes y reportes

| Pantalla | Para qué |
|---|---|
| **Km por cubierta** | Acumulado, vidas, recapados y reparaciones. Exportable a CSV. |
| **Informe semanal** | Stock al momento, movimientos de la semana (salió / entró / unidad / hora), compras y bajas del período. Se puede **exportar CSV, Excel o PDF** y **enviar por correo**. |
| **Costo / km** | Costo acumulado (compra + taller) dividido por km. Sin km no hay ratio. Exportable a CSV. |
| **Costo unidad/posición** | Suma de costos atribuidos a patente o a ubicación del chasis. Exportable a CSV. |
| **Inventario teórico** | Listado del sistema: número, estado, ubicación esperada. Exportable a CSV. |
| **Inventario físico** | Conteo en playa con diferencias respecto del sistema. |
| **Consumo** | Rendimiento y desgaste para decidir rotación, recap o baja. Exportable a CSV. |
| **Incidencias** | Eventos (pinchadura, corte, recapado, etc.). |
| **Predictivo** | Km estimados hasta 4 mm. Con dos mediciones y odómetro usa el desgaste real; si no, una estimación de catálogo. |
| **Telemetría** | Jefe/admin: ingresos, campo, planilla, mediciones, bajas e informes. Queda en la empresa, no se manda a un tercero. |
| **Movimientos** | Auditoría en castellano: quién montó, acopló, midió o dio de baja. |
| **Integridad** | Jefe/admin: cubiertas con ubicación, assignment o km que no cierran. |

### Informe semanal (paso a paso)

1. Abrí **Informes → Informe semanal**.
2. Elegí **Desde** y **Hasta** (por defecto, de lunes a hoy) y **Actualizar**.
3. Revisá stock, movimientos, compras y bajas.
4. En **Exportar o enviar**:
   - **CSV** / **Excel**: descarga el archivo.
   - **PDF**: abre la vista para imprimir o “guardar como PDF” del navegador.
   - **Enviar por correo**: indicá el destinatario y confirmá.

Si la empresa activa el envío automático (`RODANTE_WEEKLY_REPORT_ENABLED=true` con SMTP), los lunes a las 08:00 se manda el de la semana anterior a jefes/admins (o a la lista de `RODANTE_WEEKLY_REPORT_EMAILS`).

El stock del informe es el de **ahora** (cuando lo redactás), no un histórico de la semana.

Los eventos históricos **no se reescriben**. Si hay un error de km, se corrige la lectura de odómetro; el rastro queda.

---

## 11. Catálogo y usuarios (solo administrador)

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

## 12. Flujo típico de un día

1. Logística acopla el tanque al tractor e indica el km.
2. El operario abre la planilla, carga el odómetro y completa cubiertas vacías desde stock (o arrastra).
3. Si hay desgaste irregular, mide y rota (el periodo de km sigue abierto).
4. Si pincha: **Pinchadura** → la cubierta va a reparación y la ubicación queda libre → se instala otra de stock o el auxilio.
5. Al cortar el viaje, si cambia el conjunto: desacoplar con el km actual.
6. Jefe revisa **próximas a baja** en el tablero y usa **Cubiertas → Dar de baja** (o la ficha) si corresponde, o manda a recapar.
7. Gerencia mira **Km por cubierta**, **Informe semanal** y **Movimientos** sin cargar nada (rol Consulta).

---

## 13. Reglas que el sistema no negocia

- Una cubierta, una ubicación actual.
- Todo retiro pasa por stock, aunque sea de pasaje.
- Recapado abre vida nueva. Reparación no.
- El auxilio no suma km.
- La rotación no cierra el periodo de km.
- Los eventos históricos son inmutables.
- Semi, tanque o batea sin tractor acoplado no registran km.
- No se da de baja una cubierta que sigue colocada en una unidad.

---

## 14. Punto de partida (stock previo)

**Cubiertas → Punto de partida** carga el stock real que la empresa ya tenía antes de Rodante (cubiertas compradas y anotadas afuera). No es una compra: no hay proveedor ni OC.

Usá el CSV con columnas `Numero;Marca;Modelo;Medida;DOT;Km;Condicion`. Después montá en planilla lo que ya estaba en servicio. Guía, nomenclatura y glosario están en esa misma pantalla (`docs/punto-de-partida.md`).

Las compras nuevas del día a día siguen en **Cubiertas → Compras**.

---

## 15. Si algo no se puede guardar

El sistema muestra un mensaje arriba de la pantalla. Causas frecuentes:

- Km menor al último asentado.
- Cubierta ya instalada en otra unidad.
- Medida incompatible con la ubicación (295 vs 385, o diseño que no entra).
- Acoplado sin tractor al intentar operar km.
- Recapado o baja sin el rol de jefe o administrador.
- Dar de baja una cubierta que todavía está en una unidad (hay que retirarla a stock primero).
- Intentar borrar un catálogo que ya se usó (hay que desactivarlo).

Si el mensaje no alcanza, pedile a un administrador que revise **Movimientos**: ahí queda quién hizo cada acción.
