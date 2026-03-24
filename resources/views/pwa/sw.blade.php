const CACHE_NAME = 'normchat-v2';
const URLS = ['/', '/groups'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(URLS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') {
    return;
  }

  const requestUrl = new URL(event.request.url);
  const isSameOrigin = requestUrl.origin === self.location.origin;
  const isBuildAsset = requestUrl.pathname.startsWith('/build/');

  // Always fetch fresh Vite assets and third-party resources.
  if (!isSameOrigin || isBuildAsset) {
    return;
  }

  event.respondWith(caches.match(event.request).then((cached) => cached || fetch(event.request).then((response) => {
    const responseClone = response.clone();
    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseClone));
    return response;
  }).catch(() => cached)));
});
