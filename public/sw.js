const CACHE_NAME  = 'sme-v7';
const OFFLINE_URL = '/offline';

const PRECACHE_URLS = [
    '/',
    '/offline',
    '/css/app.css',
    '/js/dark-mode.js',
    '/js/offline-db.js',
    '/manifest.json',
];

// ── Install: pre-cache shell assets ──────────────────────────────────────────
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(PRECACHE_URLS).catch(() => {}))
    );
    self.skipWaiting();
});

// ── Activate: clear old caches ────────────────────────────────────────────────
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        )
    );
    self.clients.claim();
});

// ── Fetch: strategy per request type ─────────────────────────────────────────
self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') return;

    const url = new URL(event.request.url);
    if (url.origin !== location.origin) return;

    // Navigation: network-first → offline page. For the POS screen
    // specifically, a successful load is also cached, and a failed
    // (offline) load replays that last-cached snapshot instead of the
    // generic offline page — this is what lets the till reopen at all with
    // no network. Everything else keeps the plain generic-offline-page
    // fallback: caching arbitrary authenticated pages (reports, settings)
    // would mean silently serving stale financial data offline, which is a
    // worse outcome than just saying "you're offline" for those.
    const OFFLINE_CAPABLE_PAGES = ['/sales/create'];

    if (event.request.mode === 'navigate') {
        const isOfflineCapable = OFFLINE_CAPABLE_PAGES.includes(url.pathname);

        event.respondWith(
            fetch(event.request).then(response => {
                if (response.ok && isOfflineCapable) {
                    try {
                        const responseToCache = response.clone();
                        caches.open(CACHE_NAME).then(c => c.put(event.request, responseToCache)).catch(() => {});
                    } catch (e) {}
                }
                return response;
            }).catch(() => {
                const fallback = isOfflineCapable
                    ? caches.match(event.request).then(cached => cached || caches.match(OFFLINE_URL))
                    : caches.match(OFFLINE_URL);
                return fallback.then(r => r || new Response('Offline', {
                    headers: { 'Content-Type': 'text/plain' }
                }));
            })
        );
        return;
    }

    // Static assets: cache-first, update in background
    if (url.pathname.match(/\.(css|js|png|jpg|jpeg|svg|woff2?)$/)) {
        event.respondWith(
            caches.match(event.request).then(cached => {
                const fetchPromise = fetch(event.request).then(response => {
                    if (response.ok) {
                        // Clone BEFORE anything else can touch the body, and
                        // never let a failed clone/cache-write turn into an
                        // unhandled rejection on the response we're about to
                        // return to the page — a duplicate fetch racing this
                        // same request (e.g. install-time precache firing
                        // alongside an on-demand load of the same asset) can
                        // otherwise throw "Response body is already used"
                        // here, which was showing as a console error on
                        // every page load.
                        try {
                            const responseToCache = response.clone();
                            caches.open(CACHE_NAME)
                                .then(c => c.put(event.request, responseToCache))
                                .catch(() => {});
                        } catch (e) {
                            // Body already consumed by a concurrent handler
                            // for this same request — the response itself is
                            // still fine to return below, just skip caching.
                        }
                    }
                    return response;
                });
                return cached || fetchPromise;
            })
        );
        return;
    }

    // Everything else: network-first, no caching
    event.respondWith(
        fetch(event.request).catch(() => new Response(
            JSON.stringify({ error: 'offline' }),
            { status: 503, headers: { 'Content-Type': 'application/json' } }
        ))
    );
});

// ── Background Sync: flush queued sales ───────────────────────────────────────
self.addEventListener('sync', event => {
    if (event.tag !== 'sync-sales') return;

    event.waitUntil(syncSalesFromSW());
});

// The token saved with a queued sale is usually dead by the time it syncs
// (long outage, or the cashier signed out and back in). Read a fresh one
// from the till page itself before uploading.
async function freshCsrfToken() {
    try {
        const res = await fetch('/sales/create', { credentials: 'same-origin', headers: { 'Accept': 'text/html' } });
        if (!res.ok || res.redirected) return null;
        const html = await res.text();
        const m = html.match(/name="csrf-token"\s+content="([^"]+)"/) || html.match(/name="_token"\s+value="([^"]+)"/);
        return m ? m[1] : null;
    } catch (e) { return null; }
}

async function syncSalesFromSW() {
    // Open IndexedDB directly inside the SW context
    const db = await openDB();

    // Put back anything a dead earlier attempt left in 'syncing'.
    const stuck = await getAllByStatus(db, 'syncing');
    for (const r of stuck) {
        if (!r.syncing_since || (Date.now() - r.syncing_since) > 120000) {
            await setStatus(db, r.offline_id, 'pending');
        }
    }

    const pending = await getAllByStatus(db, 'pending');
    const token = pending.length ? await freshCsrfToken() : null;

    for (const record of pending) {
        try {
            await setStatus(db, record.offline_id, 'syncing');

            const fd = buildFormData(record.payload);
            if (token) fd.set('_token', token);

            const res = await fetch('/sales', {
                method:  'POST',
                headers: { 'Accept': 'application/json' },
                body:    fd,
            });

            const type = res.headers.get('content-type') || '';
            if (!type.includes('application/json')) {
                throw new Error('Your session has expired. Sign in again, then tap Retry.');
            }
            const data = await res.json();

            if (res.ok && data.success) {
                await setStatus(db, record.offline_id, 'synced', null, data.invoice_number);
                // Notify open clients so they can update their UI
                notifyClients({ type: 'sale-synced', offline_id: record.offline_id, invoice_number: data.invoice_number, redirect: data.redirect });
            } else {
                throw new Error(data.message || `HTTP ${res.status}`);
            }
        } catch (err) {
            await setStatus(db, record.offline_id, 'failed', err.message);
            notifyClients({ type: 'sale-failed', offline_id: record.offline_id, error: err.message });
        }
    }
}

function notifyClients(msg) {
    self.clients.matchAll({ type: 'window' }).then(clients =>
        clients.forEach(c => c.postMessage(msg))
    );
}

function buildFormData(payload) {
    const fd = new FormData();
    for (const [k, v] of Object.entries(payload)) {
        if (Array.isArray(v)) v.forEach(val => fd.append(k, val));
        else fd.append(k, v);
    }
    return fd;
}

// ── Minimal IndexedDB helpers (duplicated here; SW has no access to page JS) ──

function openDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open('sme-offline', 1);
        req.onupgradeneeded = e => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains('sales')) {
                const s = db.createObjectStore('sales', { keyPath: 'offline_id' });
                s.createIndex('status', 'status', { unique: false });
            }
        };
        req.onsuccess = e => resolve(e.target.result);
        req.onerror   = e => reject(e.target.error);
    });
}

function getAllByStatus(db, status) {
    return new Promise((resolve, reject) => {
        const req = db.transaction('sales', 'readonly')
            .objectStore('sales').index('status').getAll(status);
        req.onsuccess = e => resolve(e.target.result);
        req.onerror   = e => reject(e.target.error);
    });
}

function setStatus(db, offline_id, status, error = null, invoice_number = null) {
    return new Promise((resolve, reject) => {
        const tx    = db.transaction('sales', 'readwrite');
        const store = tx.objectStore('sales');
        const get   = store.get(offline_id);
        get.onsuccess = e => {
            const record = e.target.result;
            if (!record) { resolve(); return; }
            record.status = status;
            record.syncing_since = status === 'syncing' ? Date.now() : null;
            if (error          !== null) record.error          = error;
            if (invoice_number !== null) record.invoice_number = invoice_number;
            const put = store.put(record);
            put.onsuccess = () => resolve();
            put.onerror   = e2 => reject(e2.target.error);
        };
        get.onerror = e => reject(e.target.error);
    });
}
