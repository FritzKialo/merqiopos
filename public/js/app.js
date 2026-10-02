// ── Toast Notification System ──────────────────────────────────────────────

/**
 * Show a toast notification.
 * @param {string} message
 * @param {'success'|'error'|'warning'|'info'} type
 * @param {number} duration  milliseconds before auto-dismiss (default 4500)
 */
window.showToast = function (message, type = 'info', duration = 4500) {
    const container = document.getElementById('toast-container');
    if (!container || !message) return;

    const icons = {
        success: 'ph-check-circle',
        error:   'ph-warning-circle',
        warning: 'ph-warning',
        info:    'ph-info',
    };

    const toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.innerHTML = `
        <i class="ph-bold ${icons[type] || 'ph-info'} toast-icon"></i>
        <span class="toast-message">${message}</span>
        <button class="toast-close" aria-label="Dismiss">
            <i class="ph-bold ph-x"></i>
        </button>
    `;

    container.appendChild(toast);

    // Trigger entrance animation on next frame
    requestAnimationFrame(() => {
        requestAnimationFrame(() => toast.classList.add('toast-visible'));
    });

    const dismiss = () => {
        toast.classList.remove('toast-visible');
        toast.classList.add('toast-hiding');
        setTimeout(() => toast.remove(), 350);
    };

    toast.querySelector('.toast-close').addEventListener('click', dismiss);
    if (duration > 0) setTimeout(dismiss, duration);

    return toast;
};

// ── Initialise on DOM ready ────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {

    // Show server-side flash messages as toasts
    const container = document.getElementById('toast-container');
    if (container) {
        const types = ['success', 'error', 'warning', 'info'];
        types.forEach(type => {
            const msg = container.dataset[type];
            if (msg) window.showToast(msg, type);
        });
    }
});
