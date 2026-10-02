@extends('layouts.app')
@section('title', 'New Stock Adjustment')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/inventory.css') }}?v={{ @filemtime(public_path('css/inventory.css')) ?: '1' }}">
@endpush

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">New Stock Adjustment</h1>
        <p class="page-subtitle">Record an addition, deduction, or correction to a product's stock.</p>
    </div>
    <a href="{{ route('inventory.adjustments') }}" class="btn btn--outline">
        
        Back to Adjustments
    </a>
</div>

<div class="form-card">
    <form method="POST" action="{{ route('inventory.adjustments.store') }}" id="adjustmentForm">
        @csrf

        {{-- ── Product Select ────────────────────────────── --}}
        <div class="form-section-title">Product</div>

        <div class="form-group">
            <label class="form-label" for="product_id">Product *</label>
            <select
                id="product_id"
                name="product_id"
                class="form-control {{ $errors->has('product_id') ? 'is-invalid' : '' }}"
                required>
                <option value="">— Select a product —</option>
                @foreach($products as $p)
                    <option
                        value="{{ $p->id }}"
                        data-stock="{{ $p->stock_qty }}"
                        data-unit="{{ $p->unit }}"
                        {{ old('product_id', $selectedProductId) == $p->id ? 'selected' : '' }}>
                        {{ $p->name }} — {{ $p->sku }} (Stock: {{ $p->stock_qty }} {{ $p->unit }})
                    </option>
                @endforeach
            </select>
            @error('product_id')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        {{-- Current stock display --}}
        <div id="currentStockDisplay"
             style="display: none;
                    background: var(--color-surface-2);
                    border: 1px solid var(--color-border);
                    border-radius: var(--radius-md);
                    padding: var(--space-sm) var(--space-md);
                    font-size: var(--text-sm);
                    margin-bottom: var(--space-md);">
            
            Current stock: <strong id="currentStockValue">—</strong>
        </div>

        {{-- ── Adjustment Type ───────────────────────────── --}}
        <div class="form-section-title" style="margin-top: var(--space-lg);">Adjustment Type</div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: var(--space-md);">

            {{-- Addition --}}
            <label for="type_addition" style="cursor: pointer;">
                <input type="radio" id="type_addition" name="type" value="addition"
                       class="visually-hidden"
                       {{ old('type', 'addition') === 'addition' ? 'checked' : '' }}>
                <div class="type-card" id="card_addition"
                     style="border: 2px solid #16a34a;
                            border-radius: var(--radius-md);
                            padding: var(--space-md);
                            text-align: center;
                            background: #f0fdf4;
                            transition: box-shadow 0.15s;">
                    
                    <strong style="color: #15803d;">Addition</strong>
                    <p style="font-size: 0.75rem; color: #4b7a58; margin: 4px 0 0;">
                        Stock received (purchase, return)
                    </p>
                </div>
            </label>

            {{-- Deduction --}}
            <label for="type_deduction" style="cursor: pointer;">
                <input type="radio" id="type_deduction" name="type" value="deduction"
                       class="visually-hidden"
                       {{ old('type') === 'deduction' ? 'checked' : '' }}>
                <div class="type-card" id="card_deduction"
                     style="border: 2px solid #e2e8f0;
                            border-radius: var(--radius-md);
                            padding: var(--space-md);
                            text-align: center;
                            background: #fff;
                            transition: box-shadow 0.15s;">
                    
                    <strong style="color: #b91c1c;">Deduction</strong>
                    <p style="font-size: 0.75rem; color: #7f1d1d; margin: 4px 0 0;">
                        Stock removed (damage, theft, expiry)
                    </p>
                </div>
            </label>

            {{-- Correction --}}
            <label for="type_correction" style="cursor: pointer;">
                <input type="radio" id="type_correction" name="type" value="correction"
                       class="visually-hidden"
                       {{ old('type') === 'correction' ? 'checked' : '' }}>
                <div class="type-card" id="card_correction"
                     style="border: 2px solid #e2e8f0;
                            border-radius: var(--radius-md);
                            padding: var(--space-md);
                            text-align: center;
                            background: #fff;
                            transition: box-shadow 0.15s;">
                    
                    <strong style="color: #1d4ed8;">Correction</strong>
                    <p style="font-size: 0.75rem; color: #1e3a5f; margin: 4px 0 0;">
                        Set stock to exact count (stock take)
                    </p>
                </div>
            </label>
        </div>

        @error('type')
            <span class="invalid-feedback" style="display:block; margin-bottom: var(--space-sm);">{{ $message }}</span>
        @enderror

        {{-- ── Quantity & Preview ────────────────────────── --}}
        <div class="form-section-title" style="margin-top: var(--space-lg);">Quantity</div>

        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label" for="quantity_change" id="quantityLabel">
                    Quantity to Add *
                </label>
                <input
                    type="number"
                    id="quantity_change"
                    name="quantity_change"
                    class="form-control {{ $errors->has('quantity_change') ? 'is-invalid' : '' }}"
                    value="{{ old('quantity_change') }}"
                    min="0"
                    required>
                @error('quantity_change')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            {{-- New Stock Preview --}}
            <div class="form-group">
                <label class="form-label">New Stock Total (Preview)</label>
                <div id="stockPreview"
                     style="display: flex;
                            align-items: center;
                            height: 42px;
                            padding: 0 var(--space-md);
                            background: var(--color-surface-2);
                            border: 1px solid var(--color-border);
                            border-radius: var(--radius-md);
                            font-size: var(--text-sm);
                            font-weight: 600;
                            color: var(--color-text-muted);">
                    —
                </div>
            </div>
        </div>

        {{-- ── Reason ────────────────────────────────────── --}}
        <div class="form-section-title" style="margin-top: var(--space-lg);">Details</div>

        <div class="form-group">
            <label class="form-label" for="reason">Reason *</label>
            <select
                id="reason"
                name="reason"
                class="form-control {{ $errors->has('reason') ? 'is-invalid' : '' }}"
                required>
                <option value="">— Select a reason —</option>
                {{-- Options populated by JS --}}
            </select>
            @error('reason')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="notes">Notes</label>
            <textarea
                id="notes"
                name="notes"
                class="form-control {{ $errors->has('notes') ? 'is-invalid' : '' }}"
                rows="3"
                placeholder="Optional additional details about this adjustment">{{ old('notes') }}</textarea>
            @error('notes')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="reference">Reference / PO Number</label>
            <input
                type="text"
                id="reference"
                name="reference"
                class="form-control {{ $errors->has('reference') ? 'is-invalid' : '' }}"
                value="{{ old('reference') }}"
                placeholder="e.g. PO-1234 or GRN-001">
            @error('reference')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">
                
                Record Adjustment
            </button>
            <a href="{{ route('inventory.adjustments') }}" class="btn btn--outline">
                Cancel
            </a>
        </div>

    </form>
</div>

@endsection

@push('scripts')
<script>
(function () {
    // ── Reason options per type ────────────────────
    const reasonOptions = {
        addition: [
            { value: 'purchase',             label: 'Purchase / Stock Received' },
            { value: 'return_from_customer', label: 'Return from Customer' },
            { value: 'found',                label: 'Found / Recovered' },
            { value: 'correction',           label: 'Correction' },
            { value: 'other',                label: 'Other' },
        ],
        deduction: [
            { value: 'damage',     label: 'Damage' },
            { value: 'theft',      label: 'Theft / Pilferage' },
            { value: 'expiry',     label: 'Expiry / Spoilage' },
            { value: 'shrinkage',  label: 'Shrinkage' },
            { value: 'loss',       label: 'Loss' },
            { value: 'correction', label: 'Correction' },
            { value: 'other',      label: 'Other' },
        ],
        correction: [
            { value: 'stock_take',       label: 'Stock Take / Physical Count' },
            { value: 'system_correction', label: 'System Correction' },
            { value: 'other',            label: 'Other' },
        ],
    };

    // ── Quantity labels per type ───────────────────
    const qtyLabels = {
        addition:   'Quantity to Add *',
        deduction:  'Quantity to Remove *',
        correction: 'New Stock Total *',
    };

    // ── Card styling ───────────────────────────────
    const cardStyles = {
        addition:   { border: '#16a34a', bg: '#f0fdf4' },
        deduction:  { border: '#dc2626', bg: '#fef2f2' },
        correction: { border: '#2563eb', bg: '#eff6ff' },
    };

    const productSelect   = document.getElementById('product_id');
    const quantityInput   = document.getElementById('quantity_change');
    const quantityLabel   = document.getElementById('quantityLabel');
    const reasonSelect    = document.getElementById('reason');
    const stockPreview    = document.getElementById('stockPreview');
    const currentDisplay  = document.getElementById('currentStockDisplay');
    const currentValue    = document.getElementById('currentStockValue');

    const oldType   = "{{ old('type', 'addition') }}";
    const oldReason = "{{ old('reason') }}";

    // ── Helpers ────────────────────────────────────
    function selectedType() {
        const checked = document.querySelector('input[name="type"]:checked');
        return checked ? checked.value : 'addition';
    }

    function currentStock() {
        const opt = productSelect.options[productSelect.selectedIndex];
        return opt ? parseInt(opt.dataset.stock, 10) : NaN;
    }

    function currentUnit() {
        const opt = productSelect.options[productSelect.selectedIndex];
        return opt ? (opt.dataset.unit || '') : '';
    }

    function updateCardHighlights() {
        const active = selectedType();
        ['addition', 'deduction', 'correction'].forEach(function (t) {
            const card = document.getElementById('card_' + t);
            if (!card) return;
            if (t === active) {
                card.style.border        = '2px solid ' + cardStyles[t].border;
                card.style.background    = cardStyles[t].bg;
                card.style.boxShadow     = '0 0 0 3px ' + cardStyles[t].border + '33';
            } else {
                card.style.border        = '2px solid #e2e8f0';
                card.style.background    = '#fff';
                card.style.boxShadow     = 'none';
            }
        });
    }

    function updateReasonOptions(preserve) {
        const type    = selectedType();
        const options = reasonOptions[type] || [];
        reasonSelect.innerHTML = '<option value="">— Select a reason —</option>';
        options.forEach(function (opt) {
            const el       = document.createElement('option');
            el.value       = opt.value;
            el.textContent = opt.label;
            if (preserve && preserve === opt.value) {
                el.selected = true;
            }
            reasonSelect.appendChild(el);
        });
    }

    function updateQuantityLabel() {
        const type = selectedType();
        quantityLabel.textContent = qtyLabels[type] || 'Quantity *';
    }

    function updatePreview() {
        const type   = selectedType();
        const stock  = currentStock();
        const qty    = parseInt(quantityInput.value, 10);
        const unit   = currentUnit();

        if (isNaN(stock)) {
            stockPreview.textContent = '—';
            stockPreview.style.color = 'var(--color-text-muted)';
            return;
        }

        let result;
        if (type === 'addition') {
            result = isNaN(qty) ? stock : stock + qty;
        } else if (type === 'deduction') {
            result = isNaN(qty) ? stock : Math.max(0, stock - qty);
        } else {
            // correction — qty IS the new value
            result = isNaN(qty) ? stock : qty;
        }

        const diff = result - stock;
        let color = 'var(--color-text-muted)';
        if (diff > 0)       color = '#16a34a';
        else if (diff < 0)  color = '#dc2626';

        stockPreview.textContent = result + (unit ? ' ' + unit : '');
        stockPreview.style.color = color;
    }

    function updateCurrentStockDisplay() {
        const stock = currentStock();
        const unit  = currentUnit();
        if (isNaN(stock) || productSelect.selectedIndex <= 0) {
            currentDisplay.style.display = 'none';
        } else {
            currentDisplay.style.display = 'block';
            currentValue.textContent     = stock + (unit ? ' ' + unit : '');
        }
    }

    // ── Wire events ────────────────────────────────
    productSelect.addEventListener('change', function () {
        updateCurrentStockDisplay();
        updatePreview();
    });

    quantityInput.addEventListener('input', updatePreview);

    document.querySelectorAll('input[name="type"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            updateCardHighlights();
            updateQuantityLabel();
            updateReasonOptions(null);
            updatePreview();
        });
    });

    // ── Init on page load ──────────────────────────
    // Set the correct radio from old() value
    const initRadio = document.getElementById('type_' + oldType);
    if (initRadio) initRadio.checked = true;

    updateCardHighlights();
    updateQuantityLabel();
    updateReasonOptions(oldReason);
    updateCurrentStockDisplay();
    updatePreview();

})();
</script>
@endpush

