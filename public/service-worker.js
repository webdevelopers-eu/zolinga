const cacheName = 'my-cache-v1';

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(cacheName).then(cache => {
        debugger;
      return cache.addAll([
        '/',
        '/dist/system/js/web-components.js',
        // Add other static resources to cache
      ]);
    })
  );
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request).then(cachedResponse => {
      console.log('cachedResponse', cachedResponse);
      return cachedResponse || fetch(event.request).then(response => {
        // Cache new responses
        return caches.open(cacheName).then(cache => {
          cache.put(event.request, response.clone());
          return response;
        });
      });
    })
  );
});

// Napis kod, ktery zobrazi notifikaci, kdyz je stranka offline
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request).then(cachedResponse => {
      if (cachedResponse) {
        return cachedResponse;
      }
      return fetch(event.request).catch(() => {
        return new Response('You are offline');
      });
    })
  );
});