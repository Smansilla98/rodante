# Accesibilidad

Objetivo: WCAG 2.2 AA. La interfaz incluye enlace para saltar al contenido, foco visible, etiquetas explícitas, avisos de error con `role="alert"`, reducción de movimiento y controles móviles de al menos 44 px.

Los inputs usan 16 px en pantallas pequeñas para evitar zoom automático. Las tablas pasan a tarjetas y la planilla conserva desplazamiento horizontal dentro de su propio contenedor.

La suite verifica etiquetas en ingreso y recuperación de contraseña; CI también compila CSS/JS.

## axe en CI

El job `axe` de `.github/workflows/tests.yml` levanta Laravel con SQLite, instala Chromium y corre `e2e/a11y-login.spec.ts` (`@axe-core/playwright`) sobre `/login` y `/olvide-contrasena` con tags `wcag2a`, `wcag2aa`, `wcag22aa`.

Local (app en `:8093`):

```bash
npx playwright test e2e/a11y-login.spec.ts
```

No se desactivan reglas de axe sin justificación documentada aquí.
