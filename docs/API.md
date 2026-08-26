# API v1

La especificación completa está en `/openapi.yaml`. Crear un token con `POST /api/v1/auth/token` enviando `username`, `password` y opcionalmente `device_name`. En las llamadas siguientes usar:

```text
Authorization: Bearer <token>
Accept: application/json
```

Ejemplo: `GET /api/v1/me`. Revocar el token actual con `DELETE /api/v1/auth/token`.

Los resultados respetan empresa, flotas y bases del usuario. Las operaciones de escritura requieren además la capacidad correspondiente al rol y devuelven `403` si falta. No reutilizar tokens entre dispositivos.

Además de historial y operaciones:

- `GET /api/v1/tires/{id}/prediction` — pronóstico de desgaste (cálculo local; narrativa IA solo si hay `AI_API_KEY`).
- `GET /api/v1/tires/{id}/life-report` — informe de vida en JSON (timeline, costos, fotos de baja como metadatos).
- `GET /api/v1/telemetry` — eventos de operación (jefe o administrador).
- `POST /api/v1/tires/{id}/retire` acepta `photos[]` en `multipart/form-data` (hasta 6 imágenes).
