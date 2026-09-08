# Punto de partida

El **punto de partida** es la carga inicial de Rodante: catálogo, unidades (patentes), cubiertas en stock y cubiertas ya montadas en servicio. Sin este orden, marcas, medidas y posiciones no emparejan.

Esta pantalla documenta el procedimiento y el formato de planillas. Hoy el sistema importa **CSV de compras a stock**; la planilla de cubiertas montadas queda como formato previsto para la implantación.

Los códigos de posición de cada configuración se listan más abajo en **Nomenclatura de posiciones** (generados desde el catálogo del sistema).

---

## Orden de carga

1. **Bases, flotas y proveedores** — Catálogo → Bases / Flotas / Proveedores.
2. **Configuraciones de ejes** — Ya vienen del sistema (`6X4`, `4X2`, `3E-S`, etc.). Verificá que existan las que usa la flota.
3. **Marcas, modelos y medidas** — Catálogo. El modelo se identifica por **código** (ej. `FH:01`), no por el nombre largo. La medida por **código** (ej. `295/80 R22.5`). Cada modelo debe tener habilitadas las medidas que vas a usar.
4. **Unidades (patentes)** — Operación → Unidades: tipo, configuración, flota y base.
5. **Cubiertas en stock** — Compras → Importar CSV (o alta manual) → **confirmar** el borrador.
6. **Cubiertas en servicio** — Montar desde la planilla de cada unidad (hoy a mano). La planilla de arranque de la sección siguiente es el formato a completar para implantación asistida.

No mezcles en un solo archivo stock y montadas: son flujos distintos.

---

## Emparejado de catálogo

| Campo en planilla | Qué buscar en Rodante | Ejemplo correcto | Error frecuente |
|---|---|---|---|
| Marca | Nombre exacto de la marca | `Pirelli` | `PIRELLI` si en catálogo está `Pirelli` |
| Modelo | **Código** del diseño | `FH:01`, `X Multi D` | Poner el nombre (`Dirección larga distancia`) |
| Medida | **Código** de la medida | `295/80 R22.5` | `295/80R22.5` sin espacios |

Además:

- El modelo debe pertenecer a esa marca.
- La medida debe estar asociada a ese modelo.
- Todo queda acotado a la **empresa** del usuario (multiempresa).

---

## Plantilla: stock (CSV de compras)

Disponible en **Operación → Compras → Nueva compra → Importar CSV**.

- Primera fila = encabezado (se ignora el texto; el **orden** de columnas es fijo).
- Separador `;` o `,`.
- Proveedor, base y fecha van en el **formulario**, no en el archivo.
- Resultado = borrador: hay que **Confirmar** para crear los números en stock.

```text
Marca;Modelo;Medida;Cantidad;Desde Nº;Costo unitario;DOT
Pirelli;FH:01;295/80 R22.5;4;30360;185000,50;
Michelin;X Multi D;295/80 R22.5;1;30400;190000;4824
```

| Columna | Obligatorio | Notas |
|---|---|---|
| Marca | Sí | Nombre en catálogo |
| Modelo | Sí | Código en catálogo |
| Medida | Sí | Código en catálogo |
| Cantidad | Sí | Entero ≥ 1 |
| Desde Nº | No | Primer número individual; el resto es secuencia |
| Costo unitario | No | Acepta `,` o `.` |
| DOT | No | Solo válido si cantidad = 1 |

---

## Plantilla: en servicio (formato previsto)

Una fila = una cubierta montada en una posición. **Aún no hay import automático**; usala como planilla de arranque para implantación o carga asistida.

```text
Patente;Posición;Nº cubierta;Marca;Modelo;Medida;DOT;Km cubierta
AB123CD;E1_IZQ;30360;Pirelli;FH:01;295/80 R22.5;4824;45000
AB123CD;E1_DER;30361;Pirelli;FH:01;295/80 R22.5;4824;45000
AB123CD;E2_IZQ_EXT;30400;Pirelli;TH:01;295/80 R22.5;4825;52000
AB123CD;AUXILIO;30500;Pirelli;FR:01;295/80 R22.5;;0
```

| Columna | Matching |
|---|---|
| Patente | Exacta a la unidad ya cargada |
| Posición | Código de la config de esa unidad (`E1_IZQ`, `E2_IZQ_EXT`, `AUXILIO`, …) |
| Nº cubierta | Único en la empresa |
| Marca / Modelo / Medida | Igual que el CSV de stock |
| DOT | Opcional |
| Km cubierta | Kilómetros de arranque de esa cubierta (0 si es nueva) |

La posición debe existir en la **configuración de ejes** de esa patente. Un `6X4` no tiene los mismos códigos que un `3E-S`.

---

## Glosario

| Término | Significado |
|---|---|
| **Cubierta / neumático** | Unidad trazable con número individual. El historial es de la cubierta, no de la patente. |
| **Nº / número** | Identificador individual de la cubierta en la empresa. |
| **Marca** | Fabricante en catálogo (`Pirelli`, `Michelin`, …). |
| **Modelo / diseño** | Producto de la marca, identificado por **código** (`FH:01`). |
| **Medida** | Dimensión comercial por **código** (`295/80 R22.5`, `385/65 R22.5`). |
| **DOT** | Código de fabricación (semana/año). En import de compra, solo con cantidad 1. |
| **Patente** | Identificador de la unidad (tractor, camión, semi, tanque, batea). |
| **Configuración** | Layout de ejes (`6X4`, `4X2`, `3E-S`, …) que define las posiciones. |
| **Posición** | Hueco del mapa (`E2_IZQ_EXT`). Código estable para planillas. |
| **Eje** | Número de eje de adelante hacia atrás (`E1`, `E2`, …). |
| **Rol de eje** | Uso esperado: `DIRECCION`, `TRACCION`, `ARRASTRE`, `DIRECCIONAL`, `AUXILIO`. |
| **Simple** | Una cubierta por lado del eje (`_IZQ` / `_DER`). |
| **Dual / mellizas** | Dos cubiertas por lado (`_IZQ_EXT`, `_IZQ_INT`, `_DER_INT`, `_DER_EXT`). |
| **IZQ / DER** | Lado izquierdo / derecho mirando de frente hacia atrás. |
| **EXT / INT** | Exterior / interior en eje dual. |
| **AUXILIO** | Rueda de auxilio: no suma kilómetros. |
| **Stock** | Cubierta en depósito de una base, disponible para montar. |
| **En servicio** | Cubierta montada en una posición de una unidad. |
| **Vida** | Ciclo de la cubierta; un recapado abre vida nueva. |
| **Base** | Depósito / sede operativa. |
| **Flota** | Agrupación de unidades. |
| **Punto de partida** | Carga inicial ordenada para dejar la flota operativa en Rodante. |
