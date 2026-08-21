# Recuperación de contraseña

Flujo web: `/olvide-contrasena` → email con enlace → `/restablecer-contrasena/{token}`.

## Comportamiento

- Pedido por **usuario o email**.
- Mensaje genérico siempre (anti-enumeración).
- Solo envía mail si el usuario existe, está **activo** y tiene **email**.
- Token hasheado en `password_reset_tokens` (broker Laravel); expiración `config('auth.passwords.users.expire')` (60 min).
- Throttle: 5 intentos/minuto en pedido y cambio.
- Contraseña nueva: misma regla del sistema (mín. 10, letras y números).
- Token se invalida al usarse.

## Mail

En local: `MAIL_MAILER=log` (ver `.env.example`). El enlace aparece en `storage/logs/laravel.log`.

Producción: configurar SMTP/API real (`MAIL_MAILER`, `MAIL_HOST`, etc.) en Railway (`.env.railway`).

No hace falta cola para el envío síncrono; si más adelante se usa cola, `QUEUE_CONNECTION` ya está preparado.
