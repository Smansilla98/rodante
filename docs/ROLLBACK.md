# Runbook de rollback — Rodante

Objetivo: volver atrás un deploy o una migración **sin perder** el historial de planilla (assignments, movimientos, odómetros).

Las reglas de dominio no se reescriben: los `tire_movements` son inmutables. Un rollback mal hecho puede dejar el código desalineado con la DB; preferí **forward fix** cuando sea posible.

## 1. Antes de desplegar

1. Backup fresco:

   ```bash
   php artisan rodante:backup --keep=14
   # o: bash scripts/backup-mysql.sh
   ```

2. Anotá el hash del release y el nombre del `.sql.gz`.

3. Si la release trae migraciones destructivas (drop column/table), **no las merges** sin plan de forward-only.

## 2. Rollback de código (sin tocar datos)

Cuando el bug es solo de app (Blade, servicio, config) y **no** hubo migración incompatible:

1. Redeploy del commit anterior en Railway (o `git revert` + deploy).
2. Verificá `GET /up`.
3. Humo: login, abrir una planilla, montar/retirar en ambiente de staging o una unidad de prueba.

La base queda intacta. No hace falta restore.

## 3. Rollback con migración ya aplicada

### Preferido: migración compensatoria (forward)

1. Escribí una migración nueva que deshaga el cambio de esquema **sin borrar filas de negocio**.
2. Deploy + `php artisan migrate --force` (release command).
3. No uses `migrate:rollback` en producción si hay riesgo de `down()` destructivo.

### Solo si el esquema nuevo es incompatible y no podés forward-fix

1. Poner la app en mantenimiento (`php artisan down`) o detener tráfico.
2. Restore del dump previo al deploy:

   ```bash
   php artisan rodante:restore /ruta/al/backup-pre-deploy.sql.gz --force
   ```

3. Redeploy del código anterior.
4. `php artisan migrate --force` solo si el dump es anterior a migraciones que el código viejo necesita (normalmente no).
5. `php artisan up`.
6. Corré `php artisan rodante:integrity` y revisá **Consulta → Integridad**.

**Importante:** el restore pisa **toda** la base. Se pierden operaciones hechas **después** del dump. Comunicá la ventana a la flota.

## 4. Qué no hacer

- `migrate:fresh` / `db:wipe` en producción.
- `migrate:rollback` a ciegas sin leer el `down()` de cada migración.
- Editar a mano `tire_movements` para “arreglar” un mal deploy (usar `CORRECTION`).
- Borrar el volumen MySQL de Docker/Railway como atajo.

## 5. Checklist post-rollback

- [ ] `/up` responde 200
- [ ] Login con un usuario real
- [ ] Planilla de una unidad con cubiertas montadas se ve coherente
- [ ] `php artisan rodante:integrity` sin hallazgos críticos
- [ ] Backup nuevo después del rollback estable

## 6. Logs para debug remoto

Las excepciones no controladas se loguean con `user_id`, `company_id`, `path`, `method` e IP.

Conflictos de planilla (`SheetConflictException`) y reglas de dominio (`DomainException`) van a `Log::warning`.

Hallazgos de integridad (`rodante:integrity`) se registran en log cuando hay fallos.

Archivos: `storage/logs/laravel.log` (o el stack configurado en `LOG_CHANNEL`).
