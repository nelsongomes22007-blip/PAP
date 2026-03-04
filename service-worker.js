const CACHE_NAME = 'sarytha-cache-v1';
const ASSETS = [
  '/',
  '/index.php',
  '/styles.css',
  '/api/ligacao.php'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(ASSETS))
  );
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request).then(resp => {
      return resp || fetch(event.request);
    })
  );
});