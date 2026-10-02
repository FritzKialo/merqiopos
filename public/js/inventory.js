document.addEventListener('DOMContentLoaded', function () {

    // ── Profit Preview (create & edit forms) ──
    const buyingInput  = document.getElementById('buying_price');
    const sellingInput = document.getElementById('selling_price');
    const preview      = document.getElementById('profitPreview');
    const profitAmt    = document.getElementById('profitAmount');
    const profitMgn    = document.getElementById('profitMargin');

    function updateProfit() {
        if (!buyingInput || !sellingInput) return;

        var buying  = parseFloat(buyingInput.value)  || 0;
        var selling = parseFloat(sellingInput.value) || 0;
        var profit  = selling - buying;
        var margin  = buying > 0
            ? ((profit / buying) * 100).toFixed(2)
            : 0;

        if (preview) {
            preview.style.display = 'block';
            preview.style.background = 
                profit >= 0 ? '#f0fdf4' : '#fff1f2';
            preview.style.borderColor = 
                profit >= 0 ? '#86efac' : '#fca5a5';
        }

        if (profitAmt) {
            profitAmt.textContent = 
                'KSh ' + profit.toFixed(2);
            profitAmt.style.color = 
                profit >= 0 
                    ? 'var(--success)' 
                    : 'var(--danger)';
        }

        if (profitMgn) {
            profitMgn.textContent = margin + '%';
        }
    }

    if (buyingInput)  {
        buyingInput.addEventListener('input', updateProfit);
    }
    if (sellingInput) {
        sellingInput.addEventListener('input', updateProfit);
    }

    // Run once on page load (edit form)
    updateProfit();

    // ── Delete Confirmation Modal ──────────────
    window.confirmDelete = function (id, name) {
        var modal   = document.getElementById(
            'deleteModal'
        );
        var nameEl  = document.getElementById(
            'deleteProductName'
        );
        var btn     = document.getElementById(
            'confirmDeleteBtn'
        );

        if (!modal) return;

        nameEl.textContent = name;
        modal.classList.add('open');

        btn.onclick = function () {
            document.getElementById(
                'delete-' + id
            ).submit();
        };
    };

    window.closeModal = function () {
        var modal = document.getElementById(
            'deleteModal'
        );
        if (modal) modal.classList.remove('open');
    };

    // Close modal on overlay click
    var deleteModal = document.getElementById(
        'deleteModal'
    );
    if (deleteModal) {
        deleteModal.addEventListener(
            'click', 
            function (e) {
                if (e.target === deleteModal) {
                    closeModal();
                }
            }
        );
    }

    // ── Edit Category Modal ────────────────────
    window.editCategory = function (id, name, desc, parentId) {
        var modal  = document.getElementById(
            'editCategoryModal'
        );
        var form   = document.getElementById(
            'editCategoryForm'
        );
        var nameEl = document.getElementById(
            'editCategoryName'
        );
        var descEl = document.getElementById(
            'editCategoryDescription'
        );
        var parentEl = document.getElementById(
            'editCategoryParent'
        );

        if (!modal) return;

        nameEl.value = name;
        descEl.value = desc;
        // A sub-category itself is excluded from the parent list by the
        // server (see CategoryRequest) — if this row IS a top-level
        // category being edited, parentId will just be empty/undefined
        // and the select falls back to "None", which is correct either way.
        if (parentEl) parentEl.value = parentId || '';
        form.action  = '/inventory/categories/' + id;
        modal.classList.add('open');
    };

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

    // ── Bulk Selection ─────────────────────────
    var selectAll  = document.getElementById('selectAll');
    var bulkBar    = document.getElementById('bulkBar');
    var bulkCount  = document.getElementById('bulkCount');
    var rowChecks  = document.querySelectorAll('.row-check');

    function updateBulkBar() {
        var checked = document.querySelectorAll('.row-check:checked').length;
        if (bulkCount) bulkCount.textContent = checked;
        if (bulkBar) {
            bulkBar.classList.toggle('bulk-bar--visible', checked > 0);
        }
        if (selectAll) {
            selectAll.indeterminate = checked > 0 && checked < rowChecks.length;
            selectAll.checked       = checked === rowChecks.length && rowChecks.length > 0;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            rowChecks.forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
            updateBulkBar();
        });
    }

    rowChecks.forEach(function (cb) {
        cb.addEventListener('change', updateBulkBar);
    });
});