const CACHE_NAME = 'normchat-v7';
const URLS = ['/groups'];

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
  const accept = event.request.headers.get('accept') || '';
  const isHtml = event.request.mode === 'navigate' || accept.includes('text/html');
  const isJsonLike = accept.includes('application/json');
  const isAttachmentRoute = /\/groups\/\d+\/messages\/\d+\/attachment$/i.test(requestUrl.pathname);
  const isRealtimeOrAuthRoute = requestUrl.pathname.startsWith('/broadcasting/') || requestUrl.pathname.startsWith('/up');

  // Always fetch fresh for cross-origin, build assets, navigations, attachment downloads, and JSON/API calls.
  if (!isSameOrigin || isBuildAsset || isHtml || isJsonLike || isAttachmentRoute || isRealtimeOrAuthRoute) {
    event.respondWith(
      fetch(event.request).catch(() => caches.match(event.request))
    );
    return;
  }

  event.respondWith(caches.match(event.request).then((cached) => cached || fetch(event.request).then((response) => {
    const responseClone = response.clone();
    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseClone));
    return response;
  }).catch(() => cached)));
});
