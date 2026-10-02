/**
 * M-Pesa STK Push — shared JS for sale payments and subscription payments.
 *
 * Used by:
 *   resources/views/sales/payment.blade.php
 *   resources/views/settings/subscription.blade.php
 */

// ── CSRF Helper ──────────────────────────────────────────────────────────────

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

// ── Sale Payment ─────────────────────────────────────────────────────────────

/**
 * Called by the "Send STK Push" button on sales/payment.blade.php
 */
function initiateStkPush() {
    const phone  = document.getElementById('mpesaPhone')?.value?.trim();
    const saleId = document.getElementById('saleId')?.value;
    const btn    = document.getElementById('stkPushBtn');
    const status = document.getElementById('mpesaStatus');

    if (!phone) {
        showStatus(status, 'error', 'Please enter a phone number.');
        return;
    }

    if (!saleId) {
        showStatus(status, 'error', 'Sale ID missing. Refresh the page.');
        return;
    }

    btn.disabled    = true;
    btn.textContent = '⏳ Sending…';
    showStatus(status, 'info', '📲 Sending STK Push request…');

    fetch('/api/mpesa/initiate', {
        method:  'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({ sale_id: saleId, phone }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showStatus(
                status, 'info',
                '📲 Request sent! Ask the customer to check their phone and enter their M-Pesa PIN.'
            );
            btn.textContent = '⏳ Waiting for payment…';
            pollSaleStatus(saleId, btn, status);
        } else {
            showStatus(status, 'error', '❌ ' + (data.message ?? 'Failed to send STK Push.'));
            btn.disabled    = false;
            btn.textContent = '📲 Send STK Push';
        }
    })
    .catch(() => {
        showStatus(status, 'error', '❌ Network error. Please try again.');
        btn.disabled    = false;
        btn.textContent = '📲 Send STK Push';
    });
}

/**
 * Poll every 3 seconds until payment is COMPLETE or FAILED.
 */
function pollSaleStatus(saleId, btn, statusEl) {
    let attempts = 0;
    const maxAttempts = 40; // 2 minutes max

    const timer = setInterval(() => {
        attempts++;
        if (attempts > maxAttempts) {
            clearInterval(timer);
            showStatus(statusEl, 'error', '⏰ Timed out. Refresh the page to check payment status.');
            btn.disabled    = false;
            btn.textContent = '📲 Send STK Push';
            return;
        }

        fetch('/api/mpesa/status?sale_id=' + saleId)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'COMPLETE') {
                clearInterval(timer);
                const receipt = data.receipt ? ' · Receipt: ' + data.receipt : '';
                if (statusEl) showStatus(statusEl, 'success', '✅ Payment confirmed' + receipt + '! Redirecting…');
                if (btn) btn.textContent = '✅ Paid';
                const dest = window.SALE_RECEIPT_URL || window.location.href;
                setTimeout(() => { window.location.href = dest; }, 1800);
            } else if (data.status === 'FAILED') {
                clearInterval(timer);
                if (statusEl) showStatus(statusEl, 'error', '❌ Payment was cancelled or failed. You can try again.');
                if (btn) { btn.disabled = false; btn.textContent = '📲 Send STK Push'; }
            }
            // PENDING → keep polling
        })
        .catch(() => { /* ignore network hiccups, keep polling */ });
    }, 3000);
}

/**
 * Silent background poll started automatically on page load.
 * Detects any M-Pesa payment (STK push OR manual Paybill/Till) and redirects.
 */
function startBackgroundPoll(saleId) {
    if (!saleId) return;
    let attempts = 0;

    const timer = setInterval(() => {
        attempts++;
        if (attempts > 80) { clearInterval(timer); return; } // 4 min max

        fetch('/api/mpesa/status?sale_id=' + saleId)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'COMPLETE') {
                clearInterval(timer);
                const receipt  = data.receipt ? ' · ' + data.receipt : '';
                const banner   = document.getElementById('mpesaStatus') || document.getElementById('bgPayBanner');
                if (banner) showStatus(banner, 'success', '✅ M-Pesa payment received' + receipt + '! Redirecting…');
                const dest = window.SALE_RECEIPT_URL || window.location.href;
                setTimeout(() => { window.location.href = dest; }, 1800);
            }
        })
        .catch(() => {});
    }, 3000);
}

// ── Subscription Payment ─────────────────────────────────────────────────────

let activeSubscriptionModal = null;

/**
 * Open the subscription modal for the selected plan.
 * @param {string} planKey   e.g. 'business'
 * @param {number} amount    e.g. 2499
 * @param {string} planName  e.g. 'Business'
 */
function openSubscribeModal(planKey, amount, planName) {
    const modal = document.getElementById('subscribeModal');
    if (!modal) return;

    document.getElementById('modalPlanName').textContent   = planName;
    document.getElementById('modalPlanAmount').textContent = 'KSh ' + amount.toLocaleString();
    document.getElementById('modalPlanKey').value          = planKey;
    document.getElementById('modalAmount').value           = amount;

    // Reset state
    resetModalState();

    modal.style.display = 'flex';
    activeSubscriptionModal = modal;
}

function closeSubscribeModal() {
    if (activeSubscriptionModal) {
        activeSubscriptionModal.style.display = 'none';
    }
}

// Close on backdrop click
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('subscribeModal');
    if (modal) {
        modal.addEventListener('click', e => {
            if (e.target === modal) closeSubscribeModal();
        });
    }
});

function resetModalState() {
    const btn    = document.getElementById('subscribePayBtn');
    const status = document.getElementById('subscribeStatus');
    if (btn) {
        btn.disabled    = false;
        btn.textContent = '📲 Pay via M-Pesa';
    }
    if (status) {
        status.style.display = 'none';
        status.textContent   = '';
    }
}

/**
 * Trigger STK Push for a subscription payment.
 */
function initiateSubscription() {
    const phone     = document.getElementById('subPhone')?.value?.trim();
    const planKey   = document.getElementById('modalPlanKey')?.value;
    const promoCode = document.getElementById('modalPromoCode')?.value || null;
    const btn       = document.getElementById('subscribePayBtn');
    const status    = document.getElementById('subscribeStatus');

    if (!phone) {
        showStatus(status, 'error', 'Please enter your M-Pesa phone number.');
        return;
    }

    btn.disabled    = true;
    btn.textContent = '⏳ Sending…';
    showStatus(status, 'info', '📲 Sending STK Push to your phone…');

    fetch('/api/mpesa/subscribe', {
        method:  'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({ plan: planKey, phone, promo_code: promoCode }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showStatus(
                status, 'info',
                '📲 Check your phone and enter your M-Pesa PIN to complete the subscription.'
            );
            btn.textContent = '⏳ Waiting for payment…';
            pollSubscriptionStatus(data.transaction_id, btn, status);
        } else {
            showStatus(status, 'error', '❌ ' + (data.message ?? 'Failed to initiate payment.'));
            btn.disabled    = false;
            btn.textContent = '📲 Pay via M-Pesa';
        }
    })
    .catch(() => {
        showStatus(status, 'error', '❌ Network error. Please try again.');
        btn.disabled    = false;
        btn.textContent = '📲 Pay via M-Pesa';
    });
}

function pollSubscriptionStatus(transactionId, btn, statusEl) {
    let attempts  = 0;
    const maxAttempts = 40; // 2 minutes

    const timer = setInterval(() => {
        attempts++;
        if (attempts > maxAttempts) {
            clearInterval(timer);
            showStatus(statusEl, 'error', '⏰ Timed out. Refresh the page to check subscription status.');
            btn.disabled    = false;
            btn.textContent = '📲 Pay via M-Pesa';
            return;
        }

        fetch('/api/mpesa/subscription-status?transaction_id=' + transactionId)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'COMPLETE') {
                clearInterval(timer);
                showStatus(statusEl, 'success',
                    '✅ Payment confirmed! Your plan has been upgraded. Reloading…');
                setTimeout(() => window.location.reload(), 2500);
            } else if (data.status === 'FAILED') {
                clearInterval(timer);
                showStatus(statusEl, 'error', '❌ Payment was cancelled or failed. You may try again.');
                btn.disabled    = false;
                btn.textContent = '📲 Pay via M-Pesa';
            }
        })
        .catch(() => { /* keep polling */ });
    }, 3000);
}

// ── Shared Utility ────────────────────────────────────────────────────────────

function showStatus(el, type, message) {
    if (!el) return;
    el.style.display    = 'block';
    el.textContent      = message;
    el.className        = 'mpesa-status mpesa-status--' + type;

    const colors = {
        info:    { bg: '#eff6ff', border: '#93c5fd', color: '#1d4ed8' },
        success: { bg: '#f0fdf4', border: '#86efac', color: '#166534' },
        error:   { bg: '#fef2f2', border: '#fca5a5', color: '#991b1b' },
    };
    const c = colors[type] ?? colors.info;
    el.style.background   = c.bg;
    el.style.borderLeft   = '4px solid ' + c.border;
    el.style.color        = c.color;
    el.style.padding      = '10px 14px';
    el.style.borderRadius = '6px';
    el.style.fontSize     = '0.9rem';
}
