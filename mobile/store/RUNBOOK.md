# Runbook — lo que falta para publicar Rodante en Play Store

Todo lo que se podía resolver sin tu cuenta de Google/Expo ya está hecho (ver el resto de esta carpeta y el
commit correspondiente). Lo que queda acá abajo son pasos que **solo vos podés hacer**, porque dependen de tu
cuenta de Expo y de tu cuenta de Google Play — ninguna herramienta puede iniciar sesión en tu nombre ni generar
credenciales de una cuenta que no controla. Son ~15-20 minutos de clicks, en este orden.

## 0. Ya resuelto (no hace falta tocar nada de esto)

- [x] Identidad de la app (`app.config.ts`): nombre, `package`/`bundleIdentifier`, versión.
- [x] Íconos fuente (1024×1024) y perfiles de build (`eas.json`: development/preview/production/production-apk).
- [x] Versionado automático de Android (`appVersionSource: "remote"` + `autoIncrement: true` en el perfil `production` — EAS le asigna el `versionCode` solo, no hay que tocarlo a mano).
- [x] `expo-notifications` sacado (estaba instalado pero no conectado a nada — pedía permiso de notificaciones sin usarlo para nada real).
- [x] Ícono de Play Store 512×512 y gráfico de funciones 1024×500 (`mobile/store/*.png`).
- [x] Descripciones corta y larga de la ficha (`mobile/store/LISTING.md`).
- [x] Política de privacidad escrita y publicada (`mobile/store/privacy-policy.html`, URL en `LISTING.md`).
- [x] Guía para completar el formulario de "Seguridad de los datos" (`mobile/store/DATA_SAFETY.md`).
- [x] Proyecto EAS creado (`d44353e5-8326-4445-a9ea-06d914decb2e`) y `owner` (`smansillas-team`) + `projectId` ya cargados en `app.config.ts` — el paso 3 de más abajo ya está hecho, se deja documentado por si hay que recrearlo alguna vez.

## 1. Email de contacto y URL de privacidad — ✅ ya hecho

La política de privacidad ya tiene el email de contacto real (`samansilla.998@gmail.com`) y además ahora se
sirve directo desde el backend, sin depender de un link externo:

```
https://rodant-production.up.railway.app/privacidad
```

Es la URL a usar en Play Console (ver `LISTING.md`). El contenido vive en
`resources/views/legal/privacy.blade.php`; hay una copia espejo en `mobile/store/privacy-policy.html` para
referencia — si se edita el contenido más adelante, hay que actualizar los dos archivos (o pedirle a Claude que
lo haga).

## 2. Iniciar sesión en Expo/EAS

```bash
cd mobile
npx eas-cli login
```

Te va a pedir usuario/contraseña de tu cuenta de Expo (o creás una gratis en https://expo.dev/signup si no
tenés). Esto es intransferible: nadie más puede hacerlo por vos porque requiere tu login real.

## 3. Crear el proyecto EAS — ✅ ya hecho, no hace falta volver a correr `eas init`

Ya se corrió `eas init` y el proyecto quedó creado: `d44353e5-8326-4445-a9ea-06d914decb2e`, cuenta/equipo
`smansillas-team`. Como el proyecto usa configuración dinámica (`app.config.ts`), el comando no puede escribir
nada solo — los dos valores que pide (`projectId` y `owner`) ya se cargaron a mano directo en `app.config.ts`.

**Si volvés a correr `npx eas-cli init` vas a ver esto, y es esperable (no es que algo esté roto):**

```
✔ Project already linked (ID: d44353e5-8326-4445-a9ea-06d914decb2e). To re-configure, remove the "extra.eas.projectId" field from your app config.
```

Eso es un ✔ (éxito) confirmando que el proyecto ya está bien linkeado — no hace falta hacer nada más ahí. La
primera vez que se cargó el `projectId` sin el `owner`, el comando seguía este chequeo con un error porque
además pedía el `owner` y no lo encontraba (`Cannot automatically write to dynamic config`); ya está resuelto
con el campo agregado en `app.config.ts`. En resumen: **no vuelvas a correr `eas init`** salvo que quieras
crear un proyecto distinto desde cero (ahí sí te daría un `projectId` nuevo para reemplazar el actual).

## 4. Generar el build de producción

```bash
cd mobile
EAS_NO_VCS=1 npx eas-cli build --profile production --platform android
```

**Por qué falló y cómo se verificó el arreglo esta vez** (la primera corrección que hice acá quedó incompleta —
lo explico porque vale la pena que quede claro, no solo "ya está"):

Este repo es un monorepo: `mobile/` convive con el backend de Laravel en el mismo repo git. `eas-cli` arma el
paquete a subir siempre a partir de la **raíz del repositorio git** (`git rev-parse --show-toplevel`), nunca de
`mobile/` — esto pasa tanto en modo normal como con `EAS_NO_VCS=1` (lo confirmé leyendo el código fuente de
`eas-cli`, no adivinando). Al armar ese paquete, camina el árbol completo buscando qué incluir, y se choca con
`storage/framework/testing/disks` del backend — una carpeta que quedó con permisos `0770` y dueño `www-data`
(la creó un proceso de test de PHP en algún momento), así que tu usuario no puede ni siquiera listarla:

```
✖ Compressing project files
Failed to upload the project tarball to EAS Build
Reason: EACCES: permission denied, scandir '.../storage/framework/testing/disks'
```

La solución real es un `.easignore` en la **raíz del repositorio** (no alcanza con uno adentro de `mobile/`,
que es el error que cometí en el primer intento) que excluye todo el repo salvo `mobile/`. Ya está en
`.easignore` (raíz del repo). **Importante si algún día lo tocás:** la negación tiene que escribirse `!/mobile`
sin barra final — con barra final (`!/mobile/`) la librería que usa `eas-cli` no la reconoce como negación de
directorio y `mobile/` queda excluido igual, lo que produciría un build vacío en vez de un error (un bug
distinto, más silencioso). Antes de commitear esto se probó con una simulación real del recorrido de archivos
que hace `eas-cli` (mismo paquete `ignore`, mismo comportamiento de "podar" un directorio ignorado sin entrar a
leerlo) contra el filesystem real: cero errores, `storage/` nunca se toca, y los ~70 archivos reales de
`mobile/` se incluyen bien.

`EAS_NO_VCS=1` sigue siendo recomendable además del `.easignore` (evita el paso extra de clonar/diffear con
git, que sin esto también camina el repo buscando todos los `.gitignore` anidados y se puede chocar con el
mismo problema). Ya quedó como default en los 4 perfiles de `eas.json`; ponelo igual en el comando la primera
vez, por las dudas de que ese default no se cargue a tiempo antes de armar el tarball.

*Alternativa si en algún momento preferís arreglar el permiso en vez de excluir la carpeta* (no hace falta con
el `.easignore` ya puesto, y de todos modos no te ahorra subir el backend entero, que es peso muerto para este
build):
```bash
sudo chmod -R o+rX storage/framework/testing
```

Una vez que arranca bien:
- La primera vez, EAS te va a preguntar si querés que genere y gestione el keystore de firma de Android por
  vos — decile que sí, salvo que ya tengas un keystore propio de una publicación anterior (si es la primera
  vez que se publica esta app, no lo tenés).
- El build corre en la nube de Expo, tarda unos 10-15 minutos. Al terminar te da un `.aab` (Android App Bundle),
  que es el formato que pide Play Store para el track de producción.
- De paso, instalá el perfil `preview` (`EAS_NO_VCS=1 npx eas-cli build --profile preview --platform android`)
  en un teléfono real para sacar las capturas de pantalla que faltan en `LISTING.md` (mínimo 2, de pantallas
  reales con datos de ejemplo).

**✅ Build de producción generado** — `.aab` listo:
https://expo.dev/artifacts/eas/6MRrC4ging2-pn6CRSgbjBe812a0pxQZvZsoNiEY2pM.aab
(este link de Expo puede expirar con el tiempo; si eso pasa, `npx eas-cli submit --latest` igual encuentra el
build más reciente sin necesitarlo, o se puede generar uno nuevo con el mismo comando de arriba).

## 5. Crear la ficha en Google Play Console

1. Entrá a https://play.google.com/console (ya está pago, según me confirmaste).
2. Creá la app: nombre "Rodante", idioma predeterminado español (Argentina/Latam), tipo "App", gratuita.
3. Pegá las descripciones de `mobile/store/LISTING.md`.
4. Subí `mobile/store/play-store-icon-512.png` y `mobile/store/feature-graphic-1024x500.png`.
5. Subí las capturas de pantalla del paso 4.
6. Completá el cuestionario de "Seguridad de los datos" siguiendo `mobile/store/DATA_SAFETY.md` fila por fila.
7. Completá el cuestionario de clasificación de contenido (debería salir "Para todo público" con las respuestas
   de `LISTING.md`).
8. Pegá la URL de la política de privacidad (en `LISTING.md`).
9. **Recomendado, no obligatorio**: dado que Rodante es una app interna (login con cuenta de empresa, sin alta
   pública), elegí el track de **"Prueba interna"** o **"Prueba cerrada"** en vez de "Producción" pública, y
   cargá ahí los emails de las personas de tu empresa que la van a usar. Esto evita que quede listada para
   búsqueda pública sin necesidad. Si preferís que sí sea pública, se puede publicar directo a producción sin
   problema — es una decisión tuya, no técnica.

## 6. Subir el build

**Opción A — manual (no necesita nada más que lo ya hecho):** en Play Console, dentro del track elegido,
subís el archivo `.aab` que te dio `eas build` a mano.

**Opción B — automática con `eas submit`** (más cómoda para la próxima vez, pero necesita un paso extra en
Google Cloud):

1. En Google Cloud Console (con la misma cuenta de Google de tu Play Console), creá una cuenta de servicio
   con el rol "Release Manager" sobre tu app en Play Console, y descargá su clave en formato JSON.
   Guía oficial: https://docs.expo.dev/submit/android/#creating-a-service-account
2. Corré:
   ```bash
   npx eas-cli submit --platform android --latest
   ```
   Te va a pedir la ruta a ese JSON la primera vez, y de ahí en adelante puede quedar guardado en
   `eas.json` → `submit.production.android.serviceAccountKeyPath` (hoy ese bloque está vacío a propósito,
   porque esa clave es tuya y no se genera ni se sube sin tu cuenta).

## 7. Enviar a revisión

Con la ficha completa y el build subido, mandás la app a revisión desde Play Console. Google suele tardar de
unas horas a un par de días en la primera revisión.

---

Nada de este runbook se puede saltear de forma automática porque cada paso depende de una cuenta (Expo o
Google) que es tuya, no de este entorno de trabajo — no tengo ni debo tener esas credenciales.
