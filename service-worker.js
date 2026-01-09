// Service worker for fastfood_system

const CACHE_NAME = 'fastfood-cache-v1';

// Only static assets are cached
const urlsToCache = [
  '/fastfood_system/icon-192.png',
  '/fastfood_system/icon-512.png'
];

// Install event – cache static files
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(urlsToCache))
  );
  self.skipWaiting(); // Activate worker immediately
});

// Activate event – clean up old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys.filter(key => key !== CACHE_NAME)
            .map(key => caches.delete(key))
      )
    )
  );
  self.clients.claim(); // Take control of pages immediately
});

// Fetch event – only handle static assets
self.addEventListener('fetch', event => {
  const requestUrl = new URL(event.request.url);

  // Handle only static files (icons/images)
  if (requestUrl.pathname.endsWith('.png') || requestUrl.pathname.endsWith('.jpg')) {
    event.respondWith(
      caches.match(event.request).then(response => {
        return response || fetch(event.request);
      })
    );
    return; // Stop here for static assets
  }

  // All other requests (PHP pages, dynamic content) go straight to network
  // No caching or fallback for PHP pages
});