const SYNC_STORE_NAME = 'offline-requests';
const DB_NAME = 'laravel-pwa-sync';
const DB_VERSION = 1;
const SYNC_TAG = 'phc-offline-sync';

function pwaUrl(name, fallback) {
    return window.PHC_PWA_URLS?.[name] || fallback;
}

function openDB() {
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

function notify(message, type = 'info') {
    if (typeof toastr !== 'undefined') {
        toastr[type](message);

        return;
    }

    alert(message);
}

async function registerSync() {
    if (!('serviceWorker' in navigator)) {
        return false;
    }

    const registration = await navigator.serviceWorker.ready;

    if ('sync' in registration) {
        await registration.sync.register(SYNC_TAG);

        return true;
    }

    registration.active?.postMessage({ type: 'PHC_SYNC_NOW' });

    return false;
}

async function cacheCurrentPage() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    const registration = await navigator.serviceWorker.ready;

    registration.active?.postMessage({
        type: 'PHC_CACHE_URLS',
        urls: [
            window.location.href,
            pwaUrl('manifest', '/manifest.webmanifest'),
            pwaUrl('installScript', '/pwa-install.js'),
            pwaUrl('backgroundSync', '/background-sync.js'),
            pwaUrl('offline', '/offline.html'),
            pwaUrl('login', '/login'),
        ],
    });
}

async function queuePayload(payload) {
    const db = await openDB();
    const tx = db.transaction(SYNC_STORE_NAME, 'readwrite');
    const store = tx.objectStore(SYNC_STORE_NAME);

    store.add({
        ...payload,
        timestamp: Date.now(),
    });

    await new Promise((resolve, reject) => {
        tx.oncomplete = resolve;
        tx.onerror = () => reject(tx.error);
    });

    await registerSync();
}

async function queueRequest(request) {
    const headers = Object.fromEntries(request.headers.entries());
    let body = await request.clone().text();

    if (request.headers.get('content-type')?.includes('multipart/form-data')) {
        throw new Error('File uploads cannot be queued for offline sync.');
    }

    await queuePayload({
        url: request.url,
        method: request.method,
        headers,
        body,
    });
}

document.addEventListener('submit', async (event) => {
    const form = event.target;

    if (event.defaultPrevented) {
        return;
    }

    if (!form.matches('[data-offline-sync="true"]') || navigator.onLine) {
        return;
    }

    event.preventDefault();

    if (form.enctype === 'multipart/form-data') {
        notify('لا يمكن حفظ ملفات المرفقات أوفلاين. أعد المحاولة عند توفر الإنترنت.', 'error');

        return;
    }

    const body = new URLSearchParams(new FormData(form)).toString();

    await queuePayload({
        url: form.action,
        method: form.method || 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body,
    });

    form.reset();
    notify('تم حفظ الطلب أوفلاين. سيتم إرساله تلقائيًا عند رجوع الإنترنت.', 'success');
});

window.addEventListener('online', () => {
    registerSync().catch((error) => console.error('[PHC PWA] Sync registration failed:', error));
});

window.addEventListener('load', () => {
    cacheCurrentPage().catch((error) => console.error('[PHC PWA] Page cache failed:', error));
});

window.phcOfflineSync = {
    cacheCurrentPage,
    queue: queuePayload,
    queueRequest,
    registerSync,
};
