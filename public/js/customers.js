document.addEventListener('DOMContentLoaded',
    function () {

    // ── Close modal on overlay click ───────────
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

    // ── Open payment modal if validation
    //    errors exist from a failed submission ──
    @if($errors->has('payment_amount')
        || $errors->has('payment_method'))
        var modal = document.getElementById(
            'paymentModal'
        );
        if (modal) modal.classList.add('open');
    @endif

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