/* Egliane Accounting Services — Service Worker (hand-rolled) */

const VERSION = 'egliane-v7';
const SHELL_CACHE = 'egliane-shell-' + VERSION;
const DATA_CACHE = 'egliane-data-' + VERSION;

const SHELL_ASSETS = [
  '/',
  '/offline.html',
  '/manifest.json',
  '/css/app.css',
  '/css/auth.css',
  '/css/dashboard.css',
  '/js/app.js',
  '/js/auth.js',
  '/js/push.js',
  '/pwa-icons/icon-32.png',
  '/pwa-icons/icon-180.png',
  '/pwa-icons/icon-192.png',
  '/pwa-icons/icon-512.png',
  '/pwa-icons/maskable-192.png',
  '/pwa-icons/maskable-512.png',
  '/pwa-icons/logo-header.png',
  '/pwa-icons/apple-touch-icon.png',
  '/favicon.ico'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(SHELL_CACHE)
      .then((cache) => cache.addAll(SHELL_ASSETS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((key) => key !== SHELL_CACHE && key !== DATA_CACHE)
            .map((key) => caches.delete(key))
        )
      )
      .then(() => self.clients.claim())
  );
});

/* Cached pages (last viewed, network-first so fresh content wins when online) */
self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  if (request.method !== 'GET') {
    return;
  }

  if (url.origin !== self.location.origin) {
    return;
  }

  /* Navigation requests: network-first, fall back to cache, then offline page */
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          const copy = response.clone();
          caches.open(DATA_CACHE).then((cache) => cache.put(request, copy));
          return response;
        })
        .catch(async () => {
          const cached = await caches.match(request);
          if (cached) {
            return cached;
          }
          const offline = await caches.match('/offline.html');
          if (offline) {
            return offline;
          }
          return new Response('You are offline.', { status: 503, headers: { 'Content-Type': 'text/plain' } });
        })
    );
    return;
  }

  /* Static shell assets: cache-first */
  if (
    url.pathname.startsWith('/css/') ||
    url.pathname.startsWith('/js/') ||
    url.pathname.startsWith('/pwa-icons/') ||
    url.pathname === '/manifest.json' ||
    url.pathname === '/favicon.ico'
  ) {
    event.respondWith(
      caches.match(request).then((cached) => {
        const fetchPromise = fetch(request)
          .then((response) => {
            const copy = response.clone();
            caches.open(SHELL_CACHE).then((cache) => cache.put(request, copy));
            return response;
          })
          .catch(() => cached);
        return cached || fetchPromise;
      })
    );
    return;
  }

  /* Chatbot config & any other GET API-ish endpoints: network-first with cache fallback */
  if (url.pathname === '/chatbot/config') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          const copy = response.clone();
          caches.open(DATA_CACHE).then((cache) => cache.put(request, copy));
          return response;
        })
        .catch(async () => (await caches.match(request)) || new Response(JSON.stringify({ offline: true }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' }
        }))
    );
  }
});

/* Clean up old caches on message (so a deployed new SW purges stale pages) */
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

/* ---------- Push notifications ---------- */
self.addEventListener('push', (event) => {
  console.log('[SW push] event received:', event);
  let data = { title: 'Egliane', body: '', url: '/' };

  if (event.data) {
    try {
      data = { ...data, ...event.data.json() };
    } catch (e) {
      data.body = event.data.text();
    }
  }

  console.log('[SW push] showing notification:', data.title, data.body, data.url);

  event.waitUntil(
    self.registration.showNotification(data.title, {
      body: data.body,
      icon: '/pwa-icons/icon-192.png',
      badge: '/pwa-icons/icon-32.png',
      vibrate: [200, 100, 200],
      data: { url: data.url || '/' }
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  console.log('[SW notificationclick] clicked:', event.notification.title);
  event.notification.close();

  var url = (event.notification.data && event.notification.data.url) || '/';

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
      for (var i = 0; i < clientList.length; i++) {
        var client = clientList[i];
        if (client.url.indexOf(self.location.origin) === 0 && 'focus' in client) {
          client.navigate(url);
          return client.focus();
        }
      }
      return self.clients.openWindow(url);
    })
  );
});
