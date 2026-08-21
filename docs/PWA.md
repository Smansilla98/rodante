# PWA

Rodante publica `manifest.webmanifest` y registra `sw.js` al cargar sobre HTTPS o localhost. El service worker precarga `/`, `/campo` y los archivos generados por Vite.

Las navegaciones HTML y `/api/*` usan red primero. Los recursos estáticos usan caché primero. Las escrituras (`POST`, `PUT`, `PATCH`, `DELETE`) nunca se interceptan ni se encolan: sin conexión deben reintentarse manualmente para evitar operaciones duplicadas.

Después de un deploy, recargar la aplicación permite que la nueva versión del service worker tome control.
