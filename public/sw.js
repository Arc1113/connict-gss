const STATIC_CACHE = 'gss-static-v2';
const PRECACHE = [
    '/offline.html',
    '/manifest.webmanifest',
    '/favicon.svg',
    '/icons/gss-192.png',
    '/icons/gss-512.png',
    '/icons/gss-maskable-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== STATIC_CACHE)
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match('/offline.html')),
        );

        return;
    }

    const isStaticAsset =
        url.origin === self.location.origin &&
        (url.pathname.startsWith('/build/assets/') ||
            url.pathname.startsWith('/icons/') ||
            url.pathname === '/favicon.svg' ||
            url.pathname === '/manifest.webmanifest');

    if (!isStaticAsset) {
        return;
    }

    event.respondWith(
        caches.match(request).then(
            (cached) =>
                cached ||
                fetch(request).then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        void caches
                            .open(STATIC_CACHE)
                            .then((cache) => cache.put(request, copy));
                    }

                    return response;
                }),
        ),
    );
});
