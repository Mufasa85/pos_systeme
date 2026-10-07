const CACHE_VERSION = 'v7';  // ceasar a chaque version modifiée avant d'envoyer au CT modife en v2,v3,v4,v5,v6,v7 comme avec le asset.js c'est pour le cache mec ...
const CACHE_NAME = `pos-cache-${CACHE_VERSION}`;

// Installation rapide — on n'a rien à pré-cacher (Hostinger bloque parfois
// les chemins absolus lors de l'install, on fait confiance au runtime).
self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((key) => key !== CACHE_NAME)
            .map((key) => caches.delete(key))
        )
      )
      .then(() => self.clients.claim())
  );
});

// Helpers de stratégie
function isPageOrApi(request, url) {
  return (
    request.mode === 'navigate' ||
    url.pathname.endsWith('.php') ||
    url.pathname === '/' ||
    url.pathname.startsWith('/api/') ||
    url.pathname.startsWith('/media/')
  );
}

function isStaticAsset(request, url) {
  const staticPaths = ['/assets/', '/osat-sfe-pwa/icons/'];
  const staticExts = [
    '.css',
    '.js',
    '.png',
    '.jpg',
    '.jpeg',
    '.gif',
    '.svg',
    '.ico',
    '.woff',
    '.woff2',
    '.ttf',
    '.eot',
    '.xml',
  ];
  return (
    staticPaths.some((path) => url.pathname.startsWith(path)) ||
    staticExts.some((ext) => url.pathname.endsWith(ext))
  );
}

// Les icônes du manifest doivent toujours venir du réseau pour prendre
// en compte immédiatement les changements de logo après un déploiement.
function isPwaIcon(request, url) {
  const iconPaths = [
    '/assets/img/favicon',
    '/assets/img/apple-touch-icon',
    '/assets/img/android-chrome',
  ];
  return iconPaths.some((path) => url.pathname.startsWith(path));
}

function networkFirst(request) {
  return fetch(request)
    .then((networkResponse) => {
      if (networkResponse && networkResponse.ok) {
        const clone = networkResponse.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
      }
      return networkResponse;
    })
    .catch(() => {
      return caches.match(request).then((cached) => {
        if (cached) {
          return cached;
        }
        // Fallback hors ligne minimaliste
        const isApi = new URL(request.url).pathname.startsWith('/api/');
        if (isApi) {
          return new Response(
            JSON.stringify({
              success: false,
              message: 'Hors ligne',
            }),
            {
              status: 503,
              headers: { 'Content-Type': 'application/json' },
            }
          );
        }
        return new Response(
          '<h1>Hors ligne</h1><p>Impossible de charger la page sans connexion.</p>',
          {
            status: 503,
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
          }
        );
      });
    });
}

function cacheFirst(request) {
  const networkPromise = fetch(request)
    .then((networkResponse) => {
      if (networkResponse && networkResponse.ok) {
        const clone = networkResponse.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
      }
      return networkResponse;
    })
    .catch(() => undefined);

  return caches.match(request).then((cached) => {
    if (cached) {
      // Mise à jour en arrière-plan
      networkPromise.catch(() => {});
      return cached;
    }
    return networkPromise.then((networkResponse) => {
      return (
        networkResponse ||
        new Response('Ressource non disponible', { status: 404 })
      );
    });
  });
}

// Gestion des requêtes
self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // On n'intercepte que les GETs locaux
  if (request.method !== 'GET' || url.origin !== self.location.origin) {
    return;
  }

  // sw.js et manifest.json doivent rester frais (gérés par le navigateur)
  if (url.pathname === '/sw.js' || url.pathname === '/manifest.json') {
    return;
  }

  if (isPageOrApi(request, url)) {
    event.respondWith(networkFirst(request));
  } else if (isPwaIcon(request, url)) {
    // Les icônes PWA : toujours network-first pour refléter
    // immédiatement les changements de logo côté serveur.
    event.respondWith(networkFirst(request));
  } else if (isStaticAsset(request, url)) {
    event.respondWith(cacheFirst(request));
  }
});

// Permet à la page de forcer la mise à jour du SW
self.addEventListener('message', (event) => {
  if (event.data === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
