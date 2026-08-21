# API v1

La especificación completa está en `/openapi.yaml`. Crear un token con `POST /api/v1/auth/token` enviando `username`, `password` y opcionalmente `device_name`. En las llamadas siguientes usar:

```text
Authorization: Bearer <token>
Accept: application/json
```

Ejemplo: `GET /api/v1/me`. Revocar el token actual con `DELETE /api/v1/auth/token`.

Los resultados respetan empresa, flotas y bases del usuario. Las operaciones de escritura requieren además la capacidad correspondiente al rol y devuelven `403` si falta. No reutilizar tokens entre dispositivos.
