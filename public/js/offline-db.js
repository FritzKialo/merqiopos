/**
 * offline-db.js — IndexedDB wrapper for queued offline sales.
 *
 * Stores: 'sales'
 *   key: offline_id (UUID)
 *   value: { offline_id, payload, queued_at, status, error, invoice_number }
 *   status: 'pending' | 'syncing' | 'synced' | 'failed'
 */

const OfflineDB = (() => {
    const DB_NAME    = 'sme-offline';
    const DB_VERSION = 1;
    const STORE      = 'sales';

    function open() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = e => {
                const db = e.target.result;
                if (!db.objectStoreNames.contains(STORE)) {
                    const store = db.createObjectStore(STORE, { keyPath: 'offline_id' });
                    store.createIndex('status', 'status', { unique: false });
                }
            };
            req.onsuccess = e => resolve(e.target.result);
            req.onerror   = e => reject(e.target.error);
        });
    }

    async function put(record) {
        const db = await open();
        return new Promise((resolve, reject) => {
            const tx    = db.transaction(STORE, 'readwrite');
            const req   = tx.objectStore(STORE).put(record);
            req.onsuccess = () => resolve(record);
            req.onerror   = e => reject(e.target.error);
        });
    }

    async function getAll(status = null) {
        const db = await open();
        return new Promise((resolve, reject) => {
            const tx    = db.transaction(STORE, 'readonly');
            const store = tx.objectStore(STORE);
            const req   = status
                ? store.index('status').getAll(status)
                : store.getAll();
            req.onsuccess = e => resolve(e.target.result);
            req.onerror   = e => reject(e.target.error);
        });
    }

    async function remove(offline_id) {
        const db = await open();
        return new Promise((resolve, reject) => {
            const tx  = db.transaction(STORE, 'readwrite');
            const req = tx.objectStore(STORE).delete(offline_id);
            req.onsuccess = () => resolve();
            req.onerror   = e => reject(e.target.error);
        });
    }

    async function countPending() {
        const pending = await getAll('pending');
        return pending.length;
    }

    return { put, getAll, remove, countPending };
})();

/**
 * Generate a UUID v4 (used as offline_id).
 */
function generateUUID() {
    if (crypto.randomUUID) return crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
        const r = Math.random() * 16 | 0;
        return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
    });
}

/**
 * Queue a sale payload in IndexedDB.
 * Returns the offline_id assigned.
 */
async function queueOfflineSale(formData) {
    const offline_id = generateUUID();

    // Convert FormData to plain object for storage
    const payload = {};
    for (const [key, value] of formData.entries()) {
        if (key in payload) {
            if (!Array.isArray(payload[key])) payload[key] = [payload[key]];
            payload[key].push(value);
        } else {
            payload[key] = value;
        }
    }
    payload.offline_id = offline_id;

    await OfflineDB.put({
        offline_id,
        payload,
        queued_at: new Date().toISOString(),
        status:    'pending',
        error:     null,
        invoice_number: null,
    });

    return offline_id;
}

/**
 * Attempt to sync all pending sales to the server.
 * Called on page load when online, and by the service worker background sync.
 */
/**
 * A queued sale stores the CSRF token that was on the page when it was rung
 * up. Sales queued offline are often synced hours later, or after the
 * cashier signed out and back in, by which time that token is dead — the
 * server answers "session expired" and, because Retry re-sent the same dead
 * token, the sale could never sync at all. Always send the token of the page
 * that is doing the sync.
 */
async function currentCsrfToken() {
    // Ask the server first: a till page reopened from the offline cache carries
    // the token from when it was cached, which is exactly what may be dead.
    try {
        const res = await fetch('/sales/create', { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'text/html' } });
        if (res.ok && !res.redirected) {
            const html = await res.text();
            const m = html.match(/name="csrf-token"\s+content="([^"]+)"/) || html.match(/name="_token"\s+value="([^"]+)"/);
            if (m) return m[1];
        }
    } catch (e) { /* still offline, or session gone: fall through */ }
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.content) return meta.content;
    const input = document.querySelector('input[name="_token"]');
    return input ? input.value : null;
}

/** Turn a non-JSON / auth failure response into a message a cashier can act on. */
async function readSyncResponse(res) {
    const type = res.headers.get('content-type') || '';
    if (type.includes('application/json')) return await res.json();
    if (res.status === 419 || res.status === 401 || res.redirected) {
        throw new Error('Your session has expired. Sign in again, then tap Retry.');
    }
    throw new Error('Unexpected reply from the server (HTTP ' + res.status + ').');
}

// A record left in 'syncing' (tab closed or connection lost mid-upload) was
// never picked up again — only 'pending' ones are — so that sale sat
// invisible forever. After two minutes assume the attempt died.
const SYNC_STALE_MS = 2 * 60 * 1000;

async function recoverStuckSyncing() {
    const stuck = await OfflineDB.getAll('syncing');
    for (const r of stuck) {
        if (!r.syncing_since || (Date.now() - r.syncing_since) > SYNC_STALE_MS) {
            await OfflineDB.put({ ...r, status: 'pending', syncing_since: null });
        }
    }
}

async function syncPendingSales() {
    await recoverStuckSyncing();
    const pending = await OfflineDB.getAll('pending');
    if (!pending.length) return { synced: 0, failed: 0 };

    let synced = 0;
    let failed = 0;

    for (const record of pending) {
        try {
            // Mark as syncing so we don't double-send
            await OfflineDB.put({ ...record, status: 'syncing', syncing_since: Date.now() });

            // Rebuild FormData from stored payload
            const fd = new FormData();
            for (const [k, v] of Object.entries(record.payload)) {
                if (Array.isArray(v)) {
                    v.forEach(val => fd.append(k, val));
                } else {
                    fd.append(k, v);
                }
            }

            const fresh = await currentCsrfToken();
            if (fresh) fd.set('_token', fresh);

            const res = await fetch('/sales', {
                method:  'POST',
                headers: { 'Accept': 'application/json' },
                body:    fd,
            });

            const data = await readSyncResponse(res);

            if (res.ok && data.success) {
                await OfflineDB.put({
                    ...record,
                    status:         'synced',
                    syncing_since:  null,
                    invoice_number: data.invoice_number,
                });
                synced++;
            } else {
                throw new Error(data.message || 'Server error');
            }
        } catch (err) {
            await OfflineDB.put({ ...record, status: 'failed', syncing_since: null, error: err.message });
            failed++;
        }
    }

    return { synced, failed };
}

/**
 * All sales currently sitting in 'failed' status — a sale that already
 * happened in the real world (payment taken, goods handed over) but never
 * made it into the system. syncPendingSales() above only ever looks at
 * 'pending' records, so without this a failed sale sits invisible forever.
 */
async function getFailedSales() {
    return await OfflineDB.getAll('failed');
}

/**
 * Move a failed record back to 'pending' so the next sync sweep retries
 * it — for after the underlying cause is fixed (item restocked, cashier
 * logged back in after a session expiry, etc).
 */
async function retryOfflineSale(offline_id) {
    const all = await OfflineDB.getAll();
    const record = all.find(r => r.offline_id === offline_id);
    if (!record) return false;
    await OfflineDB.put({ ...record, status: 'pending', error: null });
    return true;
}

/**
 * Permanently drop a failed record. Only appropriate once the cashier has
 * confirmed the sale was already recorded some other way — this cannot be
 * undone and the sale data is gone once removed.
 */
async function discardOfflineSale(offline_id) {
    await OfflineDB.remove(offline_id);
}
