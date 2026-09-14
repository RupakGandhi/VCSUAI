/* Service worker for VCSU AI Institute for Teaching and Learning.
   Cache-first for the app shell (works fully offline after first load);
   stale-while-revalidate for the backend content API (updates when online,
   still serves the last-known-good copy when offline). */

const CACHE_VERSION = 'vcsu-shell-v1';
const SHELL_ASSETS = [
  './',
  './index.html',
  './manifest.json',
  './assets/jspdf.umd.min.js',
  './assets/fonts/inter-local.css',
  './assets/fonts/inter-latin.woff2',
  './assets/icons/icon-192.png',
  './assets/icons/icon-512.png',
  './assets/icons/icon-512-maskable.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) => cache.addAll(SHELL_ASSETS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);

  // Backend content API: stale-while-revalidate.
  if (url.pathname.startsWith('/api/v1/content')) {
    event.respondWith(
      caches.open(CACHE_VERSION).then((cache) =>
        cache.match(request).then((cached) => {
          const network = fetch(request)
            .then((response) => {
              if (response.ok) cache.put(request, response.clone());
              return response;
            })
            .catch(() => cached);
          return cached || network;
        })
      )
    );
    return;
  }

  // Don't cache other API calls (e.g. POST /events is non-GET and already
  // excluded above; any other API GETs fall through to network-only).
  if (url.pathname.startsWith('/api/')) return;

  // App shell + everything else same-origin: cache-first.
  event.respondWith(
    caches.match(request).then((cached) => {
      if (cached) return cached;
      return fetch(request).then((response) => {
        if (response.ok && url.origin === location.origin) {
          const clone = response.clone();
          caches.open(CACHE_VERSION).then((cache) => cache.put(request, clone));
        }
        return response;
      }).catch(() => cached);
    })
  );
});
