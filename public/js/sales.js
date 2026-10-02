document.addEventListener('DOMContentLoaded', function () {

    var rowIndex = 0;

    // ── Add a cart row (card style) ────────────
    window.addRow = function (productId, qty, price, bundleId, variantId, serialId) {
        var container = document.getElementById('itemsBody');
        var row       = document.createElement('div');
        row.id        = 'row-' + rowIndex;
        row.className = 'cart-row';
        row.dataset.productId = (productId || '') + (variantId ? '-' + variantId : '');
        row.dataset.rowId     = rowIndex;

        // Resolve display name and price
        var product = productId ? PRODUCTS.find(function(p){ return p.id == productId; }) : null;
        var bundle  = bundleId  ? (typeof BUNDLES !== 'undefined' ? BUNDLES.find(function(b){ return b.id == bundleId; }) : null) : null;
        var name    = product ? product.name : (bundle ? bundle.name : '—');
        if (product && variantId && product.variants) {
            var _v = product.variants.find(function(v){ return v.id == variantId; });
            if (_v) name += ' — ' + _v.name;
        }
        var unit    = product ? product.unit : '';
        var resolvedPrice = price || (product ? product.selling_price : (bundle ? bundle.price : 0));

        // Hidden product/bundle id inputs
        var idInputs = productId
            ? `<input type="hidden" name="items[${rowIndex}][product_id]" value="${productId}">`
            + (variantId ? `<input type="hidden" name="items[${rowIndex}][variant_id]" value="${variantId}">` : '')
            + (serialId  ? `<input type="hidden" name="items[${rowIndex}][serial_id]"  value="${serialId}">` : '')
            : `<input type="hidden" name="items[${rowIndex}][product_id]" value="${bundle ? bundle.items[0]?.product_id || 0 : 0}">`
            + `<input type="hidden" name="items[${rowIndex}][bundle_id]"  value="${bundleId || ''}">`;

        row.innerHTML = `
            ${idInputs}
            <div>
                <div class="cart-row-name">${name}</div>
                <div class="cart-row-price" id="price-label-${rowIndex}">KSh ${parseFloat(resolvedPrice).toFixed(2)} × <span id="qty-label-${rowIndex}">1</span> ${unit}</div>
            </div>
            <div class="qty-stepper">
                <button type="button" class="qty-btn" onclick="stepQty(${rowIndex},-1)">−</button>
                <input type="number" name="items[${rowIndex}][quantity]" class="qty-input" id="qty-${rowIndex}"
                    value="${qty || 1}" min="1" oninput="recalcRow(${rowIndex})" required>
                <button type="button" class="qty-btn" onclick="stepQty(${rowIndex},1)">+</button>
            </div>
            <input type="number" name="items[${rowIndex}][unit_price]" class="pos-price-input price-input"
                id="price-${rowIndex}" value="${resolvedPrice}" min="0" step="0.01"
                oninput="recalcRow(${rowIndex})" required>
            <div style="display:flex;gap:3px;align-items:center;position:relative;">
                <input type="number" name="items[${rowIndex}][discount]" class="pos-disc-input disc-input"
                    id="disc-${rowIndex}" value="0" min="0" max="100" step="0.01"
                    placeholder="%" title="Discount %" oninput="recalcRow(${rowIndex})">
                <button type="button" onclick="toggleDiscPicker(${rowIndex})" title="Pre-set discounts"
                    style="padding:0 4px;border:1px solid var(--color-border);border-radius:4px;background:var(--color-surface);cursor:pointer;font-size:10px;height:26px;">▾</button>
                <div id="disc-picker-${rowIndex}" style="display:none;position:absolute;top:100%;right:0;z-index:999;background:var(--color-surface);border:1px solid var(--color-border);border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.15);min-width:160px;padding:4px 0;">
                    <div style="padding:4px 10px;font-size:10px;color:var(--color-text-muted);border-bottom:1px solid var(--color-border);">DISCOUNTS</div>
                </div>
            </div>
            <span id="sub-${rowIndex}" class="pos-subtotal">KSh ${parseFloat(resolvedPrice).toFixed(2)}</span>
            <button type="button" class="pos-remove-btn" onclick="removeRow(${rowIndex})">×</button>
        `;

        container.appendChild(row);
        rowIndex++;
        if (typeof updateCartCount === 'function') updateCartCount();
        updateSummary();
    };

    // ── Remove a row ───────────────────────────
    window.removeRow = function (index) {
        var row = document.getElementById('row-' + index);
        if (row) row.remove();
        if (typeof updateCartCount === 'function') updateCartCount();
        updateSummary();
    };

    // ── Per-line discount picker ───────────────
    window.toggleDiscPicker = function (index) {
        var picker = document.getElementById('disc-picker-' + index);
        if (!picker) return;
        var isOpen = picker.style.display !== 'none';
        // Close all other pickers
        document.querySelectorAll('[id^="disc-picker-"]').forEach(function (el) {
            el.style.display = 'none';
        });
        if (isOpen) return;

        // Populate on first open
        if (!picker.dataset.populated) {
            var discounts = (typeof LINE_DISCOUNTS !== 'undefined') ? LINE_DISCOUNTS : [];
            if (discounts.length === 0) {
                picker.innerHTML += '<div style="padding:6px 10px;font-size:12px;color:var(--color-text-muted);">No active discounts</div>';
            } else {
                discounts.forEach(function (d) {
                    var label = d.type === 'percentage'
                        ? d.name + ' (' + parseFloat(d.value).toFixed(0) + '%)'
                        : d.name + ' (KSh ' + parseFloat(d.value).toFixed(2) + ' off)';
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.textContent = label;
                    btn.style.cssText = 'display:block;width:100%;text-align:left;padding:6px 10px;border:none;background:none;cursor:pointer;font-size:12px;';
                    btn.onmouseenter = function () { this.style.background = 'var(--color-hover, #f3f4f6)'; };
                    btn.onmouseleave = function () { this.style.background = 'none'; };
                    btn.onclick = function () { applyLineDiscount(index, d); };
                    picker.appendChild(btn);
                });
            }
            picker.dataset.populated = '1';
        }
        picker.style.display = 'block';
    };

    window.applyLineDiscount = function (index, discount) {
        var discInput = document.getElementById('disc-' + index);
        if (!discInput) return;

        if (discount.type === 'percentage') {
            discInput.value = parseFloat(discount.value).toFixed(2);
        } else {
            // Fixed amount — convert to % of current line total
            var qty   = parseFloat(document.getElementById('qty-'   + index)?.value) || 1;
            var price = parseFloat(document.getElementById('price-' + index)?.value) || 0;
            var lineTotal = qty * price;
            var pct = lineTotal > 0 ? Math.min(100, (discount.value / lineTotal) * 100) : 0;
            discInput.value = pct.toFixed(2);
        }
        recalcRow(index);
        document.getElementById('disc-picker-' + index).style.display = 'none';
    };

    // Close pickers on outside click
    document.addEventListener('click', function (e) {
        if (!e.target.closest('[id^="disc-picker-"]') && !e.target.closest('button[onclick^="toggleDiscPicker"]')) {
            document.querySelectorAll('[id^="disc-picker-"]').forEach(function (el) {
                el.style.display = 'none';
            });
        }
    });

    // ── Qty stepper ────────────────────────────
    window.stepQty = function (index, delta) {
        var input = document.getElementById('qty-' + index);
        if (!input) return;
        var val = parseInt(input.value) || 1;
        val = Math.max(1, val + delta);
        input.value = val;
        recalcRow(index);
    };

    // ── Auto-fill price when product selected ──
    window.onProductChange = function (select, index) {
        var opt   = select.options[select.selectedIndex];
        var price = opt.dataset.price || 0;
        var priceInput = document.getElementById(
            'price-' + index
        );
        if (priceInput) priceInput.value = price;
        recalcRow(index);
    };

    // ── Recalculate row subtotal ───────────────
    window.recalcRow = function (index) {
        var qty   = parseFloat(document.getElementById('qty-'   + index)?.value) || 0;
        var price = parseFloat(document.getElementById('price-' + index)?.value) || 0;
        var disc  = parseFloat(document.getElementById('disc-'  + index)?.value) || 0;

        var lineTotal  = qty * price;
        var discounted = lineTotal - (lineTotal * disc / 100);

        var subEl = document.getElementById('sub-' + index);
        if (subEl) subEl.textContent = 'KSh ' + discounted.toFixed(2);

        // Update qty label in card
        var qtyLabel = document.getElementById('qty-label-' + index);
        if (qtyLabel) qtyLabel.textContent = qty;

        // Show disc badge on card if discount applied
        if (disc > 0 && subEl) subEl.style.color = 'var(--color-success)';
        else if (subEl) subEl.style.color = '';

        updateSummary();
    };

    // ── Update summary panel ───────────────────
    window.updateSummary = function () {
        var subtotal = 0;
        var rows = document.querySelectorAll('#itemsBody .cart-row');

        rows.forEach(function (row) {
            var id  = row.id.replace('row-', '');
            var qty   = parseFloat(
                document.getElementById(
                    'qty-'  + id
                )?.value) || 0;
            var price = parseFloat(
                document.getElementById(
                    'price-' + id
                )?.value) || 0;
            var disc  = parseFloat(
                document.getElementById(
                    'disc-' + id
                )?.value) || 0;

            var lineTotal  = qty * price;
            var discounted = lineTotal 
                - (lineTotal * disc / 100);
            subtotal += discounted;
        });

        var discount = parseFloat(
            document.getElementById(
                'discountInput'
            )?.value) || 0;
        var total   = subtotal - discount;
        if (total < 0) total = 0;

        // M-Pesa & Bank: payment is recorded on the payment page (STK push / transfer reference)
        var method = document.getElementById('paymentMethod')?.value;
        var paidInput = document.getElementById('paidAmountInput');
        if ((method === 'mpesa' || method === 'bank_transfer') && paidInput) {
            paidInput.value = '0';
        }

        var paid    = parseFloat(paidInput?.value) || 0;
        var balance = total - paid;
        if (balance < 0) balance = 0;

        // Update display
        var sub       = document.getElementById('displaySubtotal');
        var tot       = document.getElementById('displayTotal');
        var paidEl    = document.getElementById('displayPaid');
        var balEl     = document.getElementById('displayBalance');
        var totHidden = document.getElementById('totalValue');

        if (sub) sub.textContent = 
            'KSh ' + subtotal.toFixed(2);
        if (tot) tot.textContent = 
            'KSh ' + total.toFixed(2);
        if (paidEl) paidEl.textContent = 
            'KSh ' + paid.toFixed(2);
        if (balEl) {
            balEl.textContent = 'KSh ' + balance.toFixed(2);
            balEl.style.color = balance > 0 
                ? 'var(--danger)' 
                : 'var(--success)';
        }
        if (totHidden) totHidden.value = total.toFixed(2);
    };

    // ── Toggle M-Pesa notice + lock Amount Paid ─
    window.toggleMpesaRef = function () {
        var method     = document.getElementById('paymentMethod')?.value;
        var group      = document.getElementById('mpesaRefGroup');
        var paidInput  = document.getElementById('paidAmountInput');
        var fullyPaid  = document.getElementById('fullyPaidBtn');

        var bankGroup = document.getElementById('bankNotice');
        // M-Pesa (STK push) and Bank (transfer reference) are both completed on the payment page.
        var deferred  = (method === 'mpesa' || method === 'bank_transfer');

        if (group)     group.style.display     = (method === 'mpesa') ? 'block' : 'none';
        if (bankGroup) bankGroup.style.display = (method === 'bank_transfer') ? 'block' : 'none';

        if (deferred) {
            // Force paid to 0 — actual payment is recorded on the next page
            if (paidInput) {
                paidInput.value            = '0';
                paidInput.readOnly         = true;
                paidInput.style.background = '#f3f4f6';
                paidInput.style.color      = '#9ca3af';
                paidInput.title            = 'Payment is recorded on the next step.';
            }
            if (fullyPaid) fullyPaid.style.display = 'none';
        } else {
            if (paidInput) {
                paidInput.readOnly         = false;
                paidInput.style.background = '';
                paidInput.style.color      = '';
                paidInput.title            = '';
            }
            if (fullyPaid) fullyPaid.style.display = '';
        }

        updateSummary();
    };

    // ── Handle form submit ─────────────────────
    window.handleFormSubmit = function () {
        var rows = document.querySelectorAll('#itemsBody .cart-row');
        if (rows.length === 0) {
            alert('Please add at least one product.');
            return;
        }
        document.getElementById('saleForm').submit();
    };

    // ── Initiate M-Pesa STK Push ───────────────
    window.initiateStkPush = function () {
        var phone = document.getElementById(
            'mpesaPhone'
        )?.value?.trim();

        if (!phone) {
            showMpesaStatus(
                'Please enter a phone number.',
                'error'
            );
            return;
        }

        var saleId = document.getElementById(
            'saleId'
        )?.value;

        if (!saleId) {
            showMpesaStatus(
                'Please record the sale first before '
                + 'sending STK Push.',
                'error'
            );
            return;
        }

        var btn = document.getElementById('stkPushBtn');
        btn.disabled    = true;
        btn.textContent = '⏳ Sending...';

        showMpesaStatus('Sending STK Push...', 'pending');

        fetch('/api/mpesa/initiate', {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector(
                    'meta[name="csrf-token"]'
                )?.content,
                'Accept':       'application/json',
            },
            body: JSON.stringify({
                sale_id: saleId,
                phone:   phone,
            }),
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                showMpesaStatus(
                    '✅ STK Push sent! '
                    + 'Check your phone and enter PIN.',
                    'success'
                );
                btn.textContent = '📲 Resend STK Push';
                btn.disabled    = false;
                pollMpesaStatus(saleId);
            } else {
                showMpesaStatus(
                    '❌ ' + data.message,
                    'error'
                );
                btn.textContent = '📲 Send STK Push';
                btn.disabled    = false;
            }
        })
        .catch(function () {
            showMpesaStatus(
                '❌ Network error. Please try again.',
                'error'
            );
            btn.textContent = '📲 Send STK Push';
            btn.disabled    = false;
        });
    };

    // ── Poll payment status every 5 seconds ───
    window.pollMpesaStatus = function (saleId) {
        var maxAttempts = 12; // 1 minute max
        var attempts    = 0;

        var interval = setInterval(function () {
            attempts++;

            fetch('/api/mpesa/status?sale_id=' + saleId, {
                headers: { 'Accept': 'application/json' },
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.status === 'COMPLETE') {
                    clearInterval(interval);
                    showMpesaStatus(
                        '✅ Payment confirmed! Receipt: '
                        + data.receipt,
                        'success'
                    );
                    // Auto-submit form after confirmation
                    setTimeout(function () {
                        document.getElementById(
                            'saleForm'
                        )?.submit();
                    }, 2000);

                } else if (data.status === 'FAILED') {
                    clearInterval(interval);
                    showMpesaStatus(
                        '❌ Payment failed. Please try again.',
                        'error'
                    );
                    var btn = document.getElementById(
                        'stkPushBtn'
                    );
                    if (btn) {
                        btn.textContent = '📲 Send STK Push';
                        btn.disabled    = false;
                    }
                }
            });

            if (attempts >= maxAttempts) {
                clearInterval(interval);
                showMpesaStatus(
                    '⏱ Timeout. Please check and try again.',
                    'error'
                );
            }
        }, 5000);
    };

    // ── Show M-Pesa status message ─────────────
    window.showMpesaStatus = function (message, type) {
        var el = document.getElementById('mpesaStatus');
        if (!el) return;

        el.style.display = 'block';
        el.textContent   = message;

        var styles = {
            success: {
                background: '#e8f5e9',
                color:      '#2e7d32',
                border:     '1px solid #a5d6a7',
            },
            error: {
                background: '#ffebee',
                color:      '#c62828',
                border:     '1px solid #ef9a9a',
            },
            pending: {
                background: '#fff8e1',
                color:      '#f57f17',
                border:     '1px solid #ffe082',
            },
        };

        var s = styles[type] || styles.pending;
        el.style.background   = s.background;
        el.style.color        = s.color;
        el.style.border       = s.border;
        el.style.borderRadius = '6px';
        el.style.padding      = '10px';
    };

    // ── Barcode scanning ───────────────────────
    // Works with USB/BT scanners (fast keystrokes + Enter)
    // and manual barcode entry.
    (function initBarcodeScanner() {
        var input     = document.getElementById('barcodeInput');
        var feedback  = document.getElementById('barcodeFeedback');
        var camBtn    = document.getElementById('barcodeCameraBtn');
        var videoWrap = document.getElementById('barcodeVideoWrap');
        var video     = document.getElementById('barcodeSaleVideo');
        if (!input) return;

        var feedbackTimer = null;

        function showFeedback(msg, type) {
            if (feedbackTimer) clearTimeout(feedbackTimer);
            feedback.textContent  = msg;
            feedback.className    = 'barcode-feedback ' + type;
            input.className       = 'barcode-scan-input scan-' + type;
            feedbackTimer = setTimeout(function () {
                feedback.textContent = '';
                feedback.className   = 'barcode-feedback';
                input.className      = 'barcode-scan-input';
                input.value          = '';
            }, 1800);
        }

        function findProduct(code) {
            // Strip control characters (some USB scanners append \r or \x02 etc.)
            var c = code.replace(/[\x00-\x1F\x7F]/g, '').trim().toLowerCase();
            return PRODUCTS.find(function (p) {
                return (p.barcode && p.barcode.replace(/[\x00-\x1F\x7F]/g, '').trim().toLowerCase() === c)
                    || (p.sku    && p.sku.replace(/[\x00-\x1F\x7F]/g, '').trim().toLowerCase()     === c)
                    || p.name.toLowerCase() === c;
            });
        }

        // If product already in cart, bump qty; otherwise add new row
        function addOrBump(product) {
            var rows = document.querySelectorAll('#itemsBody .cart-row');
            for (var i = 0; i < rows.length; i++) {
                var sel = rows[i].querySelector('.product-select');
                if (sel && sel.value == product.id) {
                    var rowId   = rows[i].id.replace('row-', '');
                    var qtyEl   = document.getElementById('qty-' + rowId);
                    if (qtyEl) {
                        qtyEl.value = (parseFloat(qtyEl.value) || 1) + 1;
                        recalcRow(rowId);
                    }
                    return;
                }
            }
            addRow(product.id, 1, product.selling_price);
        }

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var code = input.value.trim();
                if (!code) return;
                var product = findProduct(code);
                if (product) {
                    addOrBump(product);
                    showFeedback('Added: ' + product.name, 'success');
                } else {
                    showFeedback('Not found: ' + code, 'error');
                    feedbackTimer && clearTimeout(feedbackTimer);
                    setTimeout(function () {
                        feedback.textContent = '';
                        feedback.className   = 'barcode-feedback';
                        input.className      = 'barcode-scan-input';
                        input.value          = '';
                    }, 2200);
                }
            }
        });

        // Camera scan (BarcodeDetector API)
        if (camBtn) {
            var scanning = false;
            var stream   = null;

            camBtn.addEventListener('click', function () {
                if (scanning) {
                    // Stop camera
                    scanning = false;
                    if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
                    videoWrap.style.display = 'none';
                    camBtn.innerHTML = '<i class="ri-camera-line"></i> Camera';
                    return;
                }

                if (!('BarcodeDetector' in window)) {
                    alert('Camera barcode scanning is not supported in this browser. Use the text input instead.');
                    return;
                }

                navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                    .then(function (s) {
                        stream   = s;
                        scanning = true;
                        video.srcObject = s;
                        video.play();
                        videoWrap.style.display = 'block';
                        camBtn.innerHTML = '<i class="ri-stop-circle-line"></i> Stop';

                        var detector = new BarcodeDetector({ formats: ['ean_13','ean_8','upc_a','upc_e','code_128','code_39','qr_code'] });
                        var canvas   = document.createElement('canvas');
                        var ctx      = canvas.getContext('2d');

                        function scanLoop() {
                            if (!scanning) return;
                            canvas.width  = video.videoWidth;
                            canvas.height = video.videoHeight;
                            ctx.drawImage(video, 0, 0);
                            detector.detect(canvas).then(function (results) {
                                if (results.length > 0 && scanning) {
                                    var code    = results[0].rawValue;
                                    var product = findProduct(code);
                                    if (product) {
                                        addOrBump(product);
                                        showFeedback('Added: ' + product.name, 'success');
                                        // Brief pause before next scan
                                        setTimeout(scanLoop, 1200);
                                    } else {
                                        showFeedback('Not found: ' + code, 'error');
                                        setTimeout(scanLoop, 1200);
                                    }
                                } else {
                                    requestAnimationFrame(scanLoop);
                                }
                            }).catch(function () { requestAnimationFrame(scanLoop); });
                        }

                        video.addEventListener('loadedmetadata', scanLoop, { once: true });
                    })
                    .catch(function () {
                        alert('Could not access camera. Please type the barcode manually.');
                    });
            });
        }

        // Auto-focus barcode input on page load
        input.focus();
    })();

    // ── (Removed) previously auto-added a blank first row on every page
    // load — a leftover from an older manual line-item form. In this POS
    // UI, products are added by clicking/scanning, so the seeded row had
    // no product_id and rendered as a permanent "—" / KSh 0.00 line that
    // (a) never went away on its own, (b) silently defeated the "cart
    // must have at least one item" check below (count was never really 0),
    // letting an all-blank sale reach the server and fail validation there
    // instead. The empty-cart placeholder (#posEmpty) already covers the
    // "nothing added yet" state, so nothing needs seeding here.

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