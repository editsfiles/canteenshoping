/* =========================================================
   COLLEGE CANTEEN SERVICE WORKER (PWA)
   ========================================================= */

const CACHE_NAME = 'canteen-cache-v1';
const ASSETS_TO_PRECACHE = [
  './',
  './index.php',
  './menu.php',
  './css/style.css',
  './manifest.json',
  './uploads/Burger.jpg'
];

// Install Event: Pre-cache static assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_PRECACHE);
    }).then(() => self.skipWaiting())
  );
});

// Activate Event: Clear older caches
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

// Fetch Event: Network-first with cache fallback
self.addEventListener('fetch', (event) => {
  // Only handle GET requests
  if (event.request.method !== 'GET') return;

  event.respondWith(
    fetch(event.request)
      .then((networkResponse) => {
        // Cache successful requests for CSS / JS / images
        if (networkResponse && networkResponse.status === 200) {
          const url = event.request.url;
          if (url.endsWith('.css') || url.endsWith('.js') || url.endsWith('.jpg') || url.endsWith('.png')) {
            const responseClone = networkResponse.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(event.request, responseClone);
            });
          }
        }
        return networkResponse;
      })
      .catch(() => {
        // Offline fallback from cache
        return caches.match(event.request);
      })
  );
});
