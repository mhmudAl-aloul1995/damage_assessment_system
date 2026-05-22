const CACHE_NAME = 'phc-pwa-v4';
const OFFLINE_URL = '/offline.html';
const DB_NAME = 'laravel-pwa-sync';
const DB_VERSION = 1;
const SYNC_STORE_NAME = 'offline-requests';
const SYNC_TAG = 'phc-offline-sync';

const PRECACHE_URLS = [
    OFFLINE_URL,
    '/manifest.json',
    '/icon-192x192.png',
    '/icon-512x512.png',
    '/background-sync.js',
    '/assets/css/fontface.css',
    '/assets/css/font-unified.css',
    '/assets/plugins/global/plugins.bundle.css',
    '/assets/plugins/global/plugins.bundle.rtl.css',
    '/assets/css/style.bundle.css',
    '/assets/css/style.bundle.rtl.css',
    '/assets/media/logos/LogoGaza2.jpeg',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE_URLS.map((url) => new Request(url, { cache: 'reload' }))))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }

    if (event.data && event.data.type === 'PHC_SYNC_NOW') {
        event.waitUntil(syncOfflineRequests());
    }

    if (event.data && event.data.type === 'PHC_CACHE_URLS') {
        event.waitUntil(cacheUrls(event.data.urls || []));
    }
});

async function cacheUrls(urls) {
    const cache = await caches.open(CACHE_NAME);

    await Promise.all(urls.map(async (url) => {
        try {
            const request = new Request(url, {
                cache: 'reload',
                credentials: 'same-origin',
            });
            const response = await fetch(request);

            if (response.ok) {
                await cache.put(request, response.clone());
            }
        } catch (error) {
            console.error('[PHC PWA] Failed to cache URL:', url, error);
        }
    }));
}

function openSyncDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = (event) => {
            const db = event.target.result;

            if (!db.objectStoreNames.contains(SYNC_STORE_NAME)) {
                db.createObjectStore(SYNC_STORE_NAME, { keyPath: 'id', autoIncrement: true });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function getQueuedRequests() {
    const db = await openSyncDB();

    return new Promise((resolve, reject) => {
        const tx = db.transaction(SYNC_STORE_NAME, 'readonly');
        const request = tx.objectStore(SYNC_STORE_NAME).getAll();

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function deleteQueuedRequest(id) {
    const db = await openSyncDB();

    return new Promise((resolve, reject) => {
        const tx = db.transaction(SYNC_STORE_NAME, 'readwrite');
        const request = tx.objectStore(SYNC_STORE_NAME).delete(id);

        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error);
    });
}

async function syncOfflineRequests() {
    const queuedRequests = await getQueuedRequests();
    let syncedCount = 0;

    for (const queuedRequest of queuedRequests) {
        const response = await fetch(queuedRequest.url, {
            method: queuedRequest.method || 'POST',
            headers: queuedRequest.headers || {},
            body: queuedRequest.body || null,
            credentials: 'same-origin',
        });

        if (response.ok || (response.status >= 400 && response.status < 500)) {
            await deleteQueuedRequest(queuedRequest.id);
            syncedCount++;
        }
    }

    if (syncedCount > 0) {
        await notifyClients({
            type: 'PHC_OFFLINE_SYNC_COMPLETE',
            syncedCount,
        });
    }
}

async function notifyClients(message) {
    const clients = await self.clients.matchAll({
        includeUncontrolled: true,
        type: 'window',
    });

    clients.forEach((client) => client.postMessage(message));
}

self.addEventListener('sync', (event) => {
    if (event.tag === SYNC_TAG) {
        event.waitUntil(syncOfflineRequests());
    }
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const requestUrl = new URL(request.url);

    if (request.method !== 'GET') {
        event.respondWith(fetch(request));

        return;
    }

    if (requestUrl.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (! response.ok) {
                        return response;
                    }

                    const copy = response.clone();

                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, copy.clone());
                        cache.put(requestUrl.pathname, copy);
                    });

                    return response;
                })
                .catch(() => caches.match(request)
                    .then((cached) => cached || caches.match(requestUrl.pathname))
                    .then((cached) => cached || caches.match(OFFLINE_URL)))
        );

        return;
    }

    if (['style', 'script', 'image', 'font'].includes(request.destination)) {
        event.respondWith(
            caches.match(request).then((cached) => {
                const network = fetch(request).then((response) => {
                    if (response.ok) {
                        const copy = response.clone();

                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, copy);
                        });
                    }

                    return response;
                });

                return cached || network;
            }).catch(() => caches.match(request))
        );
    }
});
