// Offline cache for the standalone static export (D11). This file is
// deliberately NOT served on the live hosted site -- see routes/web.php's
// /site/{path} route, which 404s it there on purpose so a live client's
// edits always show on next reload. Only the downloadable ZIP ships this
// file, where the deliverable is explicitly meant to work fully offline
// after the first load (VCSU_STATIC_MODE).
const CACHE_NAME = 'vcsu-static-v1';
const CORE_ASSETS = [
  './',
  './index.html',
  './manifest.json',
  './favicon.ico',
  './assets/jspdf.umd.min.js'
];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function (cache) {
      return Promise.all(
        CORE_ASSETS.map(function (url) {
          // Best-effort per asset: an optional file missing for this client
          // (e.g. no custom PWA icons) shouldn't stop the rest from caching.
          return cache.add(url).catch(function () {});
        })
      );
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(
        keys.filter(function (key) { return key !== CACHE_NAME; })
          .map(function (key) { return caches.delete(key); })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', function (event) {
  if (event.request.method !== 'GET') return;
  event.respondWith(
    caches.match(event.request).then(function (cached) {
      if (cached) return cached;
      return fetch(event.request).then(function (response) {
        if (response && response.ok) {
          var copy = response.clone();
          caches.open(CACHE_NAME).then(function (cache) { cache.put(event.request, copy); });
        }
        return response;
      }).catch(function () {
        return cached; // undefined if never cached -- nothing more we can do offline
      });
    })
  );
});
