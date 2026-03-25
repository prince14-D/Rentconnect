const CACHE_NAME = 'rentconnect-v1';
const ASSETS_TO_CACHE = [
  '/',
  '/index.php',
  '/property.php',
  '/renter_dashboard.php',
  '/landlord_dashboard.php',
  '/about.php',
  '/services.php',
  '/contact.php',
  '/favicon.svg'
];

// Install event: cache essential assets
self.addEventListener('install', (event) => {
  console.log('[Service Worker] Installing...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('[Service Worker] Pre-caching assets');
        return cache.addAll(ASSETS_TO_CACHE).catch((error) => {
          console.warn('[Service Worker] Pre-cache failed for some assets:', error);
          // Continue even if some assets fail to cache
          return Promise.resolve();
        });
      })
      .then(() => self.skipWaiting())
  );
});

// Activate event: clean up old caches
self.addEventListener('activate', (event) => {
  console.log('[Service Worker] Activating...');
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log('[Service Worker] Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch event: network-first strategy for HTML, cache-first for assets
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip cross-origin requests
  if (url.origin !== location.origin) {
    return;
  }

  // Network-first for HTML pages
  if (request.headers.get('accept')?.includes('text/html')) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          // Cache successful responses
          if (response.status === 200) {
            const responseToCache = response.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(request, responseToCache);
            });
          }
          return response;
        })
        .catch(() => {
          // Fallback to cache if offline
          return caches.match(request)
            .then((response) => response || caches.match('/index.php'));
        })
    );
  } else {
    // Cache-first for assets (CSS, JS, images, etc.)
    event.respondWith(
      caches.match(request)
        .then((response) => response || fetch(request))
        .catch(() => {
          // Return a placeholder for images
          if (request.destination === 'image') {
            return new Response(
              '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect fill="#f0f0f0" width="100" height="100"/><text x="50" y="50" text-anchor="middle" dy=".3em" fill="#999" font-size="14">Offline</text></svg>',
              {
                headers: { 'Content-Type': 'image/svg+xml' }
              }
            );
          }
          return new Response('Offline - Resource unavailable', { status: 503 });
        })
    );
  }
});

// Handle messages from clients
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
