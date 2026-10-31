/* Portal instalável: estratégia exclusivamente de rede.
 * Dados pessoais, tokens e respostas da API nunca são armazenados em cache. */
self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
  event.respondWith(fetch(event.request));
});
