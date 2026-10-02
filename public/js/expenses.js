document.addEventListener('DOMContentLoaded',
    function () {

    // ── Toggle reference field ─────────────────
    window.toggleReference = function () {
        var method = document.getElementById(
            'payment_method'
        )?.value;
        var group  = document.getElementById(
            'referenceGroup'
        );
        if (!group) return;

        group.style.display =
            method !== 'cash' ? 'block' : 'none';
    };

    // Run once on load (for edit form)
    toggleReference();

    // ── Close modals on overlay click ──────────
    var modals = document.querySelectorAll(
        '.modal-overlay'
    );
    modals.forEach(function (overlay) {
        overlay.addEventListener('click',
            function (e) {
                if (e.target === overlay) {
                    overlay.classList.remove('open');
                }
            }
        );
    });

    // ── Auto-hide flash alerts ─────────────────
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.opacity    = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(function () {
                alert.remove();
            }, 500);
        }, 4000);
    });
});