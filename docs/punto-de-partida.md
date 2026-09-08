# Punto de partida

El **punto de partida** es el stock real **previo al sistema**: cubiertas que la empresa ya tenía compradas y anotadas en planillas, Excel u otro sistema, antes de Rodante.

No es una compra nueva. No genera orden de compra, proveedor ni costo. Es el arranque del inventario en Rodante.

Las compras posteriores (neumáticos que se compran después de salir en vivo) van por **Operación → Compras**.

---

## Orden de carga

1. **Bases, flotas** — Catálogo (y proveedores si más adelante vas a comprar).
2. **Marcas, modelos y medidas** — Catálogo. Modelo = **código** (`FH:01`). Medida = **código** (`295/80 R22.5`).
3. **Unidades (patentes)** — Operación → Unidades, con su configuración de ejes.
4. **Stock previo** — Operación → **Punto de partida** → importar CSV (esta pantalla).
5. **Cubiertas ya montadas** — Montar desde la planilla de cada unidad (o import futuro). Usá la nomenclatura de posiciones de más abajo.

---

## Emparejado de catálogo

| Campo en planilla | Qué buscar en Rodante | Ejemplo correcto |
|---|---|---|
| Marca | Nombre exacto | `Pirelli` |
| Modelo | **Código** del diseño | `FH:01` |
| Medida | **Código** de la medida | `295/80 R22.5` |

El modelo debe ser de esa marca y la medida debe estar habilitada en ese modelo.

---

## Plantilla: stock previo (CSV)

En esta pantalla: **Cargar stock previo**.

```text
Numero;Marca;Modelo;Medida;DOT;Km;Condicion
30360;Pirelli;FH:01;295/80 R22.5;4824;45000;USADA
30361;Pirelli;FH:01;295/80 R22.5;4825;1200;NUEVA_USADA
;Michelin;X Multi D;295/80 R22.5;;0;NUEVA
```

| Columna | Obligatorio | Notas |
|---|---|---|
| Numero | No | Vacío = siguiente número libre |
| Marca | Sí | Nombre en catálogo |
| Modelo | Sí | Código en catálogo |
| Medida | Sí | Código en catálogo |
| DOT | No | Único en la empresa |
| Km | No | Kilómetros ya recorridos (default 0) |
| Condicion | No | Default `USADA`. Valores: `NUEVA`, `NUEVA_USADA`, `USADA`, `RECAPADA`, `REPARADA` |

La **base** y la **fecha de corte** van en el formulario.

---

## Plantilla: en servicio (formato previsto)

Una fila = cubierta ya montada. Todavía se carga montando desde la planilla; esta planilla sirve de relevamiento.

```text
Patente;Posición;Nº cubierta;Marca;Modelo;Medida;DOT;Km cubierta
AB123CD;E1_IZQ;30360;Pirelli;FH:01;295/80 R22.5;4824;45000
AB123CD;AUXILIO;30500;Pirelli;FR:01;295/80 R22.5;;0
```

La posición debe existir en la configuración de esa patente (`E1_IZQ`, `E2_IZQ_EXT`, `AUXILIO`, …).

---

## Glosario

| Término | Significado |
|---|---|
| **Punto de partida** | Carga del stock real previo a Rodante. |
| **Stock previo** | Cubiertas ya compradas afuera, que entran al depósito sin OC. |
| **Compra** | Ingreso comercial nuevo (después del arranque). |
| **Cubierta** | Unidad trazable con número individual. |
| **Nº** | Identificador individual en la empresa. |
| **Marca / Modelo / Medida** | Catálogo: nombre de marca; códigos de modelo y medida. |
| **DOT** | Código de fabricación (semana/año). |
| **Condición** | Estado físico al ingresar (`USADA`, `NUEVA`, …). |
| **Patente** | Unidad (tractor, camión, semi, tanque, batea). |
| **Posición** | Hueco del mapa (`E2_IZQ_EXT`, `AUXILIO`). |
| **Configuración** | Layout de ejes (`6X4`, `3E-S`, …). |
| **Base** | Depósito / sede donde queda el stock. |
