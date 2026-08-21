# Escala 10k–100k

Crear datos en lotes y medir:

```bash
php artisan rodante:seed-scale 10000 --company=1
php artisan rodante:bench-scale --company=1 --json
COUNT=1000 scripts/bench-scale.sh
```

## Resultados medidos (MySQL local Docker, 2026-08-21)

| Volumen | tires_index | inventory_report | csv_stream_start |
|---|---|---|---|
| ~10 044 | 30 ms / 6 q | 42 ms / 8 q | 8 ms |
| ~50 044 | 83 ms / 6 q | 193 ms / 8 q | 8 ms |
| ~100 044 | 178 ms / 6 q | 443 ms / 8 q | 7 ms |

## Cuellos de botella

1. **Inventario agregado** (`ReportService::inventory`): crece ~lineal con el stock; hidrata relaciones. Punto de presión principal hacia 100k+ (sub-segundo aún, pero no constante).
2. **Listado paginado**: queries estables (6); latencia crece por índice/tamaño de tabla, aceptable.
3. **CSV**: el inicio del stream es barato; el export completo es O(n) por diseño (`chunkById`).

Índice añadido: `tires(company_id, status, individual_number)`.

Para comparar: misma base, sin caché caliente distinta, mismo motor.
