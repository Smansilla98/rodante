const CACHE = 'rodante-shell-v2';
const SHELL = ['/', '/campo', '/manifest.webmanifest', '/favicon.png'];

async function builtAssets() {
  try {
    const response = await fetch('/build/manifest.json', { cache: 'no-store' });
    if (!response.ok) return [];
    const manifest = await response.json();
    return Object.values(manifest).flatMap((entry) => [
      entry.file && `/build/${entry.file}`,
      ...(entry.css || []).map((file) => `/build/${file}`),
    ]).filter(Boolean);
  } catch {
    return [];
  }
}

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE);
    const assets = [...SHELL, ...await builtAssets()];
    await Promise.allSettled(assets.map((url) => cache.add(url)));
    await self.skipWaiting();
  })());
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const names = await caches.keys();
    await Promise.all(names.filter((name) => name !== CACHE).map((name) => caches.delete(name)));
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return; // nunca encolar ni repetir escrituras offline

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  const isApi = url.pathname.startsWith('/api/');
  const isHtml = request.mode === 'navigate' || request.headers.get('accept')?.includes('text/html');
  if (isApi || isHtml) {
    event.respondWith(fetch(request).catch(async () => {
      if (isHtml) return (await caches.match(request)) || (await caches.match('/'));
      return new Response(JSON.stringify({ message: 'Sin conexión' }), {
        status: 503,
        headers: { 'Content-Type': 'application/json' },
      });
    }));
    return;
  }

  event.respondWith(caches.match(request).then((cached) => cached || fetch(request).then((response) => {
    if (response.ok) {
      const copy = response.clone();
      caches.open(CACHE).then((cache) => cache.put(request, copy));
    }
    return response;
  })));
});
