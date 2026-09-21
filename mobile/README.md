# Rodante Mobile (Expo)

App nativa Android/iOS de campo. Consume la API Sanctum de Laravel (`/api/v1`).

## Desarrollo

```bash
cp .env.example .env
# EXPO_PUBLIC_API_URL=http://TU_IP:8000/api/v1
npm install
npm start
# o: npm run start:clean  (start.sh, con fixes de inotify/polling en Linux)
```

## Autenticación

Rodante usa un único token Bearer de Sanctum (`POST /api/v1/auth/token`, 30 días de
vigencia) — **no hay refresh token**. Un 401 en cualquier request limpia la sesión
local y vuelve a `/login`. Ver `docs/MOBILE_PLATFORM.md` en la raíz del repo.

## Tests

```bash
npm run typecheck
npm test
```

## Builds store

```bash
npm i -g eas-cli
eas login
eas init   # genera el projectId real — no hay uno hardcodeado todavía
eas build --platform android --profile production   # AAB
eas build --platform android --profile production-apk  # APK (sideload/QA)
eas build --platform ios --profile production
```

Perfiles en `eas.json`: development / preview / production / production-apk.

Bundle IDs: `com.rodante.app` — ver `docs/MOBILE_PLATFORM.md`.

## Pendiente antes de un build de producción real

- Correr `eas init` y pegar el `projectId` real en `EAS_PROJECT_ID` (ver `.env.example`).
- Definir la URL pública real del backend (Railway u otro) y setearla como
  `EXPO_PUBLIC_API_URL` en el perfil `production` de `eas.json`.
- Subir fotos de incidente/baja (`TirePhotoController` existe en el backend web pero
  no está expuesto en `routes/api.php` — ver el follow-up documentado en
  `docs/MOBILE_PLATFORM.md`).
