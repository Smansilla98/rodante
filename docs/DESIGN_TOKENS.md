# Tokens de diseño

Los tokens viven en `resources/css/app.css`, dentro de `:root`.

- Color: `--color-primary`, `--color-primary-hover`, `--color-surface`, `--color-text`, `--color-border`.
- Superficies originales: `--page`, `--card`, `--card-2`, `--sidebar`.
- Estado: `--ok`, `--warn`, `--danger`.
- Interacción: `--tap` (44 px), `--focus`.
- Geometría: `--sw`, `--th`, `--r`, `--shadow`.

Los alias `--color-*` permiten migrar componentes sin romper los nombres históricos. Los botones primarios consumen `--primary` mediante `--g-purple`; conservar contraste AA al modificar ambos.
