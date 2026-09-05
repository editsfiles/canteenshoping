/* =========================================================
   COLLEGE CANTEEN SERVICE WORKER (PWA)
   ========================================================= */

const CACHE_NAME = 'canteen-cache-v2';
const STATIC_ASSETS = [
  './manifest.json',
  './css/style.css',
  './uploads/Burger.jpg'
];

// Install Event: Pre-cache core styling and icons
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS);
    }).then(() => self.skipWaiting())
  );
});

// Activate Event: Clear outdated caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((name) => {
          if (name !== CACHE_NAME) {
            return caches.delete(name);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Event: Network-first for pages/API, Cache-first for static assets
self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);

  // Never cache payment, authentication, or live status checking endpoints
  if (
    url.pathname.includes('check_uropay_status') ||
    url.pathname.includes('uropay_payment') ||
    url.pathname.includes('webhook') ||
    url.pathname.includes('create_order') ||
    url.pathname.includes('check_payment')
  ) {
    return; // Pass through to network directly
  }

  // Static assets: Cache falling back to network
  if (
    url.pathname.endsWith('.css') ||
    url.pathname.endsWith('.js') ||
    url.pathname.endsWith('.jpg') ||
    url.pathname.endsWith('.jpeg') ||
    url.pathname.endsWith('.png') ||
    url.pathname.endsWith('.woff2')
  ) {
    event.respondWith(
      caches.match(event.request).then((cachedResponse) => {
        if (cachedResponse) {
          return cachedResponse;
        }
        return fetch(event.request).then((networkResponse) => {
          if (networkResponse && networkResponse.status === 200) {
            const copy = networkResponse.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(event.request, copy);
            });
          }
          return networkResponse;
        });
      })
    );
    return;
  }

  // Dynamic pages: Network-first with offline fallback
  event.respondWith(
    fetch(event.request).catch(() => {
      return caches.match(event.request).then((cached) => {
        if (cached) return cached;
        return caches.match('./index.php');
      });
    })
  );
});
