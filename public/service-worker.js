/**
 * ============================================================
 * UASKTE App — Service Worker
 * Network-first caching strategy with offline fallback.
 * ============================================================
 */

const CACHE_NAME = 'uaskte-v1';

const PRECACHE_ASSETS = [
  '/uaskte/public/assets/css/style.css',
  '/uaskte/public/assets/js/app.js',
  '/uaskte/public/assets/js/otp.js',
  '/uaskte/public/assets/js/webauthn.js',
  '/uaskte/public/assets/js/admin.js',
  '/uaskte/public/manifest.json',
  '/uaskte/public/offline.html',
];


/* ── Install: Precache Static Assets ─────────────────────── */

self.addEventListener('install', (event) => {
  console.log('[SW] Install — precaching assets');
  event.waitUntil(
    caches
      .open(CACHE_NAME)
      .then((cache) => cache.addAll(PRECACHE_ASSETS))
      .then(() => self.skipWaiting())
  );
});


/* ── Activate: Clean Old Caches ──────────────────────────── */

self.addEventListener('activate', (event) => {
  console.log('[SW] Activate — cleaning old caches');
  event.waitUntil(
    caches
      .keys()
      .then((cacheNames) =>
        Promise.all(
          cacheNames
            .filter((name) => name !== CACHE_NAME)
            .map((name) => {
              console.log('[SW] Deleting old cache:', name);
              return caches.delete(name);
            })
        )
      )
      .then(() => self.clients.claim())
  );
});


/* ── Fetch: Network-First with Cache Fallback ────────────── */

self.addEventListener('fetch', (event) => {
  const { request } = event;

  // Only handle GET requests
  if (request.method !== 'GET') return;

  event.respondWith(
    fetch(request)
      .then((networkResponse) => {
        // Clone the response to store in cache
        const responseClone = networkResponse.clone();
        caches.open(CACHE_NAME).then((cache) => {
          cache.put(request, responseClone);
        });
        return networkResponse;
      })
      .catch(async () => {
        // Network failed — try cache
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
          return cachedResponse;
        }

        // For navigation requests, serve the offline page
        if (request.mode === 'navigate') {
          const offlinePage = await caches.match('/uaskte/public/offline.html');
          if (offlinePage) {
            return offlinePage;
          }
        }

        // For everything else, return a basic error response
        return new Response('Network error occurred.', {
          status: 503,
          statusText: 'Service Unavailable',
          headers: new Headers({ 'Content-Type': 'text/plain' }),
        });
      })
  );
});
