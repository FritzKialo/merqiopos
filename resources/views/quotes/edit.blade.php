@extends('layouts.app')
@section('title', 'Edit ' . $quote->quote_number)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/sales.css') }}">
    <style>
        /* ── Quote form layout ────────────────── */
        .quote-layout {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: var(--space-5);
            align-items: start;
        }

        @media (max-width: 1024px) {
            .quote-layout { grid-template-columns: 1fr; }
        }

        @media (max-width: 640px) {
            .quote-edit-form-grid { grid-template-columns: 1fr !important; }
        }

        .form-card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-5);
            box-shadow: var(--shadow-xs);
        }

        .form-section-title {
            font-size: var(--text-sm);
            font-weight: 700;
            color: var(--color-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.07em;
            margin-bottom: var(--space-4);
            padding-bottom: var(--space-3);
            border-bottom: 1px solid var(--color-border);
        }

        .items-table-wrapper { overflow-x: auto; margin-bottom: var(--space-4); }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th {
            background: var(--color-surface-2);
            padding: 8px 10px;
            text-align: left;
            font-size: var(--text-2xs);
            font-weight: 700;
            color: var(--color-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.07em;
            border-bottom: 1px solid var(--color-border);
            white-space: nowrap;
        }

        .items-table td {
            padding: 6px 8px;
            border-bottom: 1px solid var(--color-border);
            vertical-align: middle;
        }

        .items-table tr:last-child td { border-bottom: none; }

        .add-item-btn {
            background: none;
            border: 1px dashed var(--color-border);
            border-radius: var(--radius-md);
            padding: 8px 16px;
            color: var(--color-primary);
            font-size: var(--text-sm);
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.18s;
        }

        .add-item-btn:hover {
            background: var(--color-primary-light);
            border-color: var(--color-primary);
        }

        .remove-row-btn {
            background: none;
            border: none;
            color: var(--color-danger);
            cursor: pointer;
            font-size: 1.2rem;
            padding: 2px 6px;
            border-radius: var(--radius-sm);
            line-height: 1;
        }

        .remove-row-btn:hover { background: #fef0f3; }

        .quote-summary-panel {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-5);
            box-shadow: var(--shadow-xs);
            position: sticky;
            top: var(--space-4);
        }

        .quote-summary-panel h3 {
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--color-text);
            margin-bottom: var(--space-4);
            padding-bottom: var(--space-3);
            border-bottom: 1px solid var(--color-border);
        }

        .q-summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--space-2) 0;
            font-size: var(--text-sm);
            color: var(--color-text-muted);
            border-bottom: 1px solid var(--color-border);
        }

        .q-summary-row:last-of-type { border-bottom: none; }

        .q-summary-row.total {
            font-weight: 700;
            font-size: var(--text-base);
            color: var(--color-text);
            padding-top: var(--space-3);
        }
    </style>
@endpush

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">
             Edit {{ $quote->quote_number }}
        </h1>
        <p class="page-subtitle">Update quote details and line items.</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="{{ route('quotes.show', $quote) }}" class="btn btn--outline">
             Back to Quote
        </a>
    </div>
</div>

@if(session('error'))
    <div class="alert alert-danger" style="margin-bottom: var(--space-4);">
        
        {{ session('error') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom: var(--space-4);">
        
        <div>
            <strong>Please fix the following errors:</strong>
            <ul style="margin: 6px 0 0 16px; padding: 0;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<form method="POST" action="{{ route('quotes.update', $quote) }}" id="quoteForm">
    @csrf
    @method('PUT')

    <div class="quote-layout">

        {{-- LEFT: Items --}}
        <div>
            <div class="form-card">
                <div class="form-section-title">
                     Line Items
                </div>

                @error('items')
                    <div class="alert alert-danger" style="margin-bottom: var(--space-3);">
                        {{ $message }}
                    </div>
                @enderror

                <div class="items-table-wrapper">
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th style="width: 30%;">Product</th>
                                <th style="width: 25%;">Description</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Subtotal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            {{-- Rows injected by JS --}}
                        </tbody>
                    </table>
                </div>

                <button type="button" class="add-item-btn" onclick="addQuoteRow()">
                     Add Line Item
                </button>
            </div>
        </div>

        {{-- RIGHT: Quote Details + Summary --}}
        <div>
            <div class="form-card" style="margin-bottom: var(--space-4);">
                <div class="form-section-title">
                     Quote Details
                </div>

                <div class="form-group">
                    <label class="form-label">Customer (optional)</label>
                    <select name="customer_id" class="form-control">
                        <option value="">— No customer —</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}"
                                {{ old('customer_id', $quote->customer_id) == $c->id ? 'selected' : '' }}>
                                {{ $c->name }}{{ $c->phone ? ' ('.$c->phone.')' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('customer_id')
                        <span style="display:block; color: var(--color-danger); font-size: var(--text-xs); margin-top: 4px;">{{ $message }}</span>
                    @enderror
                </div>

                <div class="quote-edit-form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3);">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Quote Date *</label>
                        <input type="date" name="quote_date"
                               class="form-control {{ $errors->has('quote_date') ? 'is-invalid' : '' }}"
                               value="{{ old('quote_date', $quote->quote_date->format('Y-m-d')) }}" required>
                        @error('quote_date')
                            <span style="display:block; color: var(--color-danger); font-size: var(--text-xs); margin-top: 4px;">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Valid Until</label>
                        <input type="date" name="valid_until"
                               class="form-control {{ $errors->has('valid_until') ? 'is-invalid' : '' }}"
                               value="{{ old('valid_until', $quote->valid_until?->format('Y-m-d')) }}">
                        @error('valid_until')
                            <span style="display:block; color: var(--color-danger); font-size: var(--text-xs); margin-top: 4px;">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Summary Panel --}}
            <div class="quote-summary-panel">
                <h3> Summary</h3>

                <div class="q-summary-row">
                    <span>Subtotal</span>
                    <span id="displaySubtotal">KSh 0.00</span>
                </div>

                <div class="q-summary-row">
                    <span>Discount (KSh)</span>
                    <input type="number" name="discount_amount" id="discountInput"
                           value="{{ old('discount_amount', $quote->discount_amount) }}"
                           min="0" step="0.01"
                           style="width: 90px; padding: 4px 8px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: var(--text-sm); text-align: right; background: var(--color-surface-2); color: var(--color-text);"
                           oninput="updateQuoteSummary()">
                </div>

                <div class="q-summary-row">
                    <span>Tax Rate (%)</span>
                    <input type="number" name="tax_rate" id="taxRateInput"
                           value="{{ old('tax_rate', $quote->tax_rate) }}"
                           min="0" max="100" step="0.01"
                           style="width: 70px; padding: 4px 8px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: var(--text-sm); text-align: right; background: var(--color-surface-2); color: var(--color-text);"
                           oninput="updateQuoteSummary()">
                </div>

                <div class="q-summary-row">
                    <span>Tax Amount</span>
                    <span id="displayTax">KSh 0.00</span>
                </div>

                <div class="q-summary-row total">
                    <span>Total</span>
                    <span id="displayTotal">KSh 0.00</span>
                </div>

                <div class="form-group" style="margin-top: var(--space-4);">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"
                              placeholder="Optional notes for the customer...">{{ old('notes', $quote->notes) }}</textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Terms & Conditions</label>
                    <textarea name="terms" class="form-control" rows="3"
                              placeholder="Payment terms, delivery conditions...">{{ old('terms', $quote->terms) }}</textarea>
                </div>

                <div style="margin-top: var(--space-4);">
                    <button type="button" class="btn btn--primary" style="width: 100%;" onclick="handleQuoteSubmit()">
                         Update Quote
                    </button>
                </div>
            </div>
        </div>

    </div>
</form>

@endsection

@push('scripts')
<script>
    var PRODUCTS = @json($products);
    var EXISTING_ITEMS = @json($quote->items);
    var quoteRowIndex = 0;

    function addQuoteRow(productId, productName, description, qty, price) {
        var tbody = document.getElementById('itemsBody');
        var row   = document.createElement('tr');
        row.id    = 'qrow-' + quoteRowIndex;

        var options = '<option value="">— Select product —</option>';
        PRODUCTS.forEach(function(p) {
            var selected = (productId && productId == p.id) ? 'selected' : '';
            options += '<option value="' + p.id + '" data-price="' + p.selling_price + '" data-name="' + p.name.replace(/"/g, '&quot;') + '" ' + selected + '>'
                + p.name + '</option>';
        });

        var idx = quoteRowIndex;

        row.innerHTML = `
            <td data-label="Product">
                <select name="items[${idx}][product_id]"
                        class="form-control product-select"
                        onchange="onQuoteProductChange(this, ${idx})"
                        style="min-width: 130px;">
                    ${options}
                </select>
                <input type="hidden"
                       name="items[${idx}][product_name]"
                       id="qpname-${idx}"
                       value="${productName || ''}">
            </td>
            <td data-label="Description">
                <input type="text"
                       name="items[${idx}][description]"
                       class="form-control"
                       value="${description || ''}"
                       placeholder="Optional"
                       style="min-width: 110px;">
            </td>
            <td data-label="Qty">
                <input type="number"
                       name="items[${idx}][quantity]"
                       class="form-control"
                       id="qqty-${idx}"
                       value="${qty || 1}"
                       min="1"
                       style="width: 65px;"
                       oninput="recalcQuoteRow(${idx})"
                       required>
            </td>
            <td data-label="Unit Price">
                <input type="number"
                       name="items[${idx}][unit_price]"
                       class="form-control"
                       id="qprice-${idx}"
                       value="${price || 0}"
                       min="0"
                       step="0.01"
                       style="width: 95px;"
                       oninput="recalcQuoteRow(${idx})"
                       required>
            </td>
            <td data-label="Subtotal">
                <span id="qsub-${idx}" style="font-weight: 600; white-space: nowrap;">KSh 0.00</span>
            </td>
            <td data-label="">
                <button type="button" class="remove-row-btn" onclick="removeQuoteRow(${idx})">&times;</button>
            </td>
        `;

        tbody.appendChild(row);
        quoteRowIndex++;

        // Recalc the subtotal display for the newly added row
        recalcQuoteRow(idx);
        updateQuoteSummary();
    }

    function removeQuoteRow(index) {
        var row = document.getElementById('qrow-' + index);
        if (row) row.remove();
        updateQuoteSummary();
    }

    function onQuoteProductChange(select, index) {
        var opt   = select.options[select.selectedIndex];
        var price = opt.dataset.price || 0;
        var name  = opt.dataset.name  || '';

        var priceInput = document.getElementById('qprice-' + index);
        var nameField  = document.getElementById('qpname-' + index);

        if (priceInput) priceInput.value = price;
        if (nameField && name) nameField.value = name;
        if (!opt.value && nameField) nameField.value = '';

        recalcQuoteRow(index);
    }

    function recalcQuoteRow(index) {
        var qty   = parseFloat(document.getElementById('qqty-'   + index)?.value) || 0;
        var price = parseFloat(document.getElementById('qprice-' + index)?.value) || 0;
        var sub   = qty * price;

        var subEl = document.getElementById('qsub-' + index);
        if (subEl) subEl.textContent = 'KSh ' + sub.toFixed(2);

        updateQuoteSummary();
    }

    function updateQuoteSummary() {
        var subtotal = 0;
        document.querySelectorAll('#itemsBody tr').forEach(function(row) {
            var id    = row.id.replace('qrow-', '');
            var qty   = parseFloat(document.getElementById('qqty-'   + id)?.value) || 0;
            var price = parseFloat(document.getElementById('qprice-' + id)?.value) || 0;
            subtotal += qty * price;
        });

        var discount = parseFloat(document.getElementById('discountInput')?.value) || 0;
        var taxRate  = parseFloat(document.getElementById('taxRateInput')?.value)  || 0;
        var tax      = subtotal * taxRate / 100;
        var total    = subtotal - discount + tax;
        if (total < 0) total = 0;

        var subEl = document.getElementById('displaySubtotal');
        var taxEl = document.getElementById('displayTax');
        var totEl = document.getElementById('displayTotal');

        if (subEl) subEl.textContent = 'KSh ' + subtotal.toFixed(2);
        if (taxEl) taxEl.textContent = 'KSh ' + tax.toFixed(2);
        if (totEl) totEl.textContent = 'KSh ' + total.toFixed(2);
    }

    function handleQuoteSubmit() {
        var rows = document.querySelectorAll('#itemsBody tr');
        if (rows.length === 0) {
            alert('Please add at least one line item.');
            return;
        }

        var valid = true;
        rows.forEach(function(row) {
            var id        = row.id.replace('qrow-', '');
            var nameField = document.getElementById('qpname-' + id);
            var select    = row.querySelector('select[name*="product_id"]');

            if (nameField && !nameField.value.trim()) {
                if (select && select.selectedIndex > 0) {
                    nameField.value = select.options[select.selectedIndex].dataset.name || select.options[select.selectedIndex].text;
                } else {
                    alert('Please select a product or enter a product name for each line item.');
                    valid = false;
                    return false;
                }
            }
        });

        if (valid) {
            document.getElementById('quoteForm').submit();
        }
    }

    // Auto-hide alerts
    document.querySelectorAll('.alert').forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(function() { alert.remove(); }, 500);
        }, 4000);
    });

    // Load existing items from the quote
    EXISTING_ITEMS.forEach(function(item) {
        addQuoteRow(item.product_id, item.product_name, item.description, item.quantity, item.unit_price);
    });

    // If no existing items, add one blank row
    if (EXISTING_ITEMS.length === 0) {
        addQuoteRow();
    }
</script>
@endpush
