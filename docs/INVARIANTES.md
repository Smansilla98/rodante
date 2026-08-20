# Invariantes de Rodante

Estas reglas viven en servicios y, cuando es posible, en la base. No se corrigen “editando el historial”.

1. **Una ubicación actual.** `tire_current_locations.tire_id` es unique. `LocationService::place` hace `updateOrCreate`.
2. **Todo retiro pasa por STOCK.** No se monta desde reparación ni reserva. El pasaje a taller sale de stock.
3. **Recapado = vida nueva.** `IncidentType::Recapado` cierra la vida y abre la siguiente.
4. **Reparación (parche) ≠ vida nueva.** Condición `REPARADA`, misma `life_number`.
5. **Auxilio no suma km.** `counts_km` del assignment/segmento se apaga en posición spare.
6. **Rotación no cierra el assignment.** Cambia posición; el período de km sigue abierto.
7. **Movimientos inmutables.** `TireMovement` no se updatea ni borra. Una corrección genera `CORRECTION`.
8. **Configuraciones.** Solo el catálogo cargado. No hay `6X24`, `6X1`, `7X24` ni `6X1X1` como códigos activos.
9. **Un assignment abierto por cubierta.** Unique `open_tire_id` (nullable) + observer + CHECK MySQL (`chk_assignment_open_consistency`). Detalle en `docs/DOMAIN_INVARIANTS.md`.
10. **Multiempresa.** Toda consulta de negocio filtra `company_id`. Un admin no ve otra empresa.

Chequeo operativo: `php artisan rodante:integrity` y pantalla **Consulta → Integridad**.

Ampliación (open_key, odómetro↔km, numeración concurrente): `docs/DOMAIN_INVARIANTS.md`.
