document.addEventListener('DOMContentLoaded',
    function () {

    // ── Password strength checker ──────────────
    window.checkStrength = function (val) {
        var info  = document.getElementById(
            'strengthInfo'
        );
        var label = document.getElementById(
            'strengthLabel'
        );
        var bar   = document.getElementById(
            'strengthBar'
        );

        if (!info || !label || !bar) return;

        if (val.length === 0) {
            info.style.display = 'none';
            return;
        }

        info.style.display = 'block';

        var score = 0;
        if (val.length >= 8)           score++;
        if (val.length >= 12)          score++;
        if (/[A-Z]/.test(val))         score++;
        if (/[0-9]/.test(val))         score++;
        if (/[^A-Za-z0-9]/.test(val))  score++;

        var levels = [
            { label: 'Very Weak', color: '#dc2626',
              width: '20%' },
            { label: 'Weak',      color: '#f97316',
              width: '40%' },
            { label: 'Fair',      color: '#ca8a04',
              width: '60%' },
            { label: 'Strong',    color: '#16a34a',
              width: '80%' },
            { label: 'Very Strong',color: '#15803d',
              width: '100%' },
        ];

        var level = levels[
            Math.min(score - 1, levels.length - 1)
        ] || levels[0];

        label.textContent        = level.label;
        label.style.color        = level.color;
        bar.style.width          = level.width;
        bar.style.background     = level.color;
    };

    // ── Edit member modal ──────────────────────
    window.openEditModal = function (
        id, name, email, role
    ) {
        var modal = document.getElementById(
            'editMemberModal'
        );
        var form  = document.getElementById(
            'editMemberForm'
        );

        if (!modal || !form) return;

        document.getElementById(
            'editName').value  = name;
        document.getElementById(
            'editEmail').value = email;

        var roleSelect = document.getElementById(
            'editRole'
        );
        for (var i = 0; i < roleSelect.options.length;
             i++) {
            if (roleSelect.options[i].value === role) {
                roleSelect.selectedIndex = i;
                break;
            }
        }

        form.action = '/settings/team/' + id;
        modal.classList.add('open');
    };

    // ── Open add modal if validation errors ────
    // (flag is set by the Blade view before this script loads)
    if (window.__teamHasErrors) {
        var addModal = document.getElementById(
            'addMemberModal'
        );
        if (addModal) addModal.classList.add('open');
    }

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