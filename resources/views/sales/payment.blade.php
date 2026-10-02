@extends('layouts.app')
@section('title', 'Record Payment — ' . $sale->invoice_number)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/sales.css') }}?v={{ @filemtime(public_path('css/sales.css')) ?: '1' }}">
@endpush

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Record Payment</h1>
        <p class="page-subtitle">Invoice: <strong>{{ $sale->invoice_number }}</strong></p>
    </div>
    <a href="{{ route('sales.show', $sale) }}" class="btn btn--outline">&#8592; Back to Sale</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

@if($sale->payment_status === 'paid')
    <div class="payment-paid-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <h3>This sale is fully paid.</h3>
        <a href="{{ route('sales.show', $sale) }}" class="btn btn--primary">View Sale</a>
    </div>
@else

@php
    $mpesaEnabled   = $sale->business->hasFeature('mpesa_sales') && $sale->business->hasMpesaConfigured();
    $pesapalEnabled = $sale->business->hasPesapalConfigured();
    $customerPhone  = $sale->customer?->phone ?? '';
    // Only one method's fields are shown at a time (tabs) so the page fits
    // one screen instead of stacking every method's full form vertically.
    $defaultTab = $mpesaEnabled ? 'mpesa' : ($pesapalEnabled ? 'pesapal' : 'manual');
@endphp

<div class="payment-page">
<div class="sale-layout">

    {{-- LEFT: Summary --}}
    <div>
        <div class="form-card">
            <div class="form-section-title">Sale Summary</div>

            <div class="payment-summary-block">
                <span class="payment-summary-label">Customer</span>
                <p class="payment-summary-name">{{ $sale->customer?->name ?? 'Walk-in Customer' }}</p>
                @if($customerPhone)
                <p class="payment-summary-phone">{{ $customerPhone }}</p>
                @endif
            </div>

            <div class="items-table-wrapper">
            <table class="items-table">
                <thead><tr><th>Product</th><th>Qty</th><th>Subtotal</th></tr></thead>
                <tbody>
                    @foreach($sale->items as $item)
                    <tr>
                        <td data-label="Product">{{ $item->product_name }}</td>
                        <td data-label="Qty">{{ $item->quantity }}</td>
                        <td data-label="Subtotal">KSh {{ number_format($item->subtotal, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>

            <div>
                <div class="summary-row total">
                    <span>Total</span><span>KSh {{ number_format($sale->total_amount, 2) }}</span>
                </div>
                @if($sale->paid_amount > 0)
                <div class="summary-row paid">
                    <span>Already Paid</span><span>KSh {{ number_format($sale->paid_amount, 2) }}</span>
                </div>
                @endif
                <div class="summary-row balance">
                    <span>Balance Due</span><span>KSh {{ number_format($sale->balance_due, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- RIGHT: Payment --}}
    <div class="summary-panel">
        <h3>Choose Payment Method</h3>

        <div class="payment-tabs">
            @if($mpesaEnabled)
            <button type="button" class="payment-tab-btn {{ $defaultTab === 'mpesa' ? 'active' : '' }}" data-tab="mpesa" onclick="showPaymentTab('mpesa')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                M-Pesa
            </button>
            <button type="button" class="payment-tab-btn" data-tab="mpesa-qr" onclick="showPaymentTab('mpesa-qr')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><line x1="14" y1="14" x2="14" y2="21"/><line x1="21" y1="14" x2="21" y2="21"/><line x1="14" y1="17.5" x2="21" y2="17.5"/></svg>
                Scan QR
            </button>
            @endif
            @if($pesapalEnabled)
            <button type="button" class="payment-tab-btn {{ $defaultTab === 'pesapal' ? 'active' : '' }}" data-tab="pesapal" onclick="showPaymentTab('pesapal')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                Card
            </button>
            @endif
            <button type="button" class="payment-tab-btn {{ $defaultTab === 'manual' ? 'active' : '' }}" data-tab="manual" onclick="showPaymentTab('manual')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Cash / Other
            </button>
        </div>

        {{-- M-Pesa STK Push --}}
        @if($mpesaEnabled)
        <div class="payment-tab-panel" data-panel="mpesa" @if($defaultTab !== 'mpesa') hidden @endif>
            <p class="payment-option-subtitle" style="margin-bottom:1rem;">Send a payment request directly to the customer's phone.</p>

            <div class="form-group">
                <label class="form-label">Customer Phone Number *</label>
                <input type="tel" id="mpesaPhone" class="form-control"
                    value="{{ $customerPhone }}"
                    placeholder="07XX XXX XXX"
                    style="font-size:1.1rem; font-weight:600; letter-spacing:0.05em;">
                <span class="form-hint">The M-Pesa prompt will be sent to this number.</span>
            </div>

            <div class="form-group">
                <label class="form-label">Amount (KSh)</label>
                <input type="number" id="mpesaAmount" class="form-control"
                    value="{{ $sale->balance_due }}" min="1" max="{{ $sale->balance_due }}"
                    style="font-size:1.1rem; font-weight:700;">
                <span class="form-hint">Max: KSh {{ number_format($sale->balance_due, 2) }}</span>
            </div>

            <button type="button" id="stkPushBtn" onclick="initiateStkPush()"
                class="btn btn--mpesa" style="width:100%; font-size:1rem; padding:14px;">
                Send STK Push to Customer
            </button>

            <div id="mpesaStatus" style="display:none; margin-top:12px;"></div>
        </div>
        @elseif($sale->business->hasFeature('mpesa_sales'))
        <div class="payment-tab-panel" data-panel="mpesa" hidden>
            <div class="alert alert-warning">
                M-Pesa STK Push not configured.
                <a href="{{ route('settings.mpesa') }}" style="font-weight:700;">Set up in Settings → M-Pesa</a>
            </div>
        </div>
        @endif

        {{-- M-Pesa QR — no phone number needed, the customer scans it themselves.
        Settles as an ordinary Till/Paybill payment (same C2B webhook as a
        manually-typed till payment), so this page's background poll (already
        running for the STK tab above) picks up completion the same way. --}}
        @if($mpesaEnabled)
        <div class="payment-tab-panel" data-panel="mpesa-qr" hidden>
            <p class="payment-option-subtitle" style="margin-bottom:1rem;">Let the customer scan this with their M-Pesa app — no phone number needed.</p>

            <div id="qrGenerateWrap">
                <button type="button" id="qrGenerateBtn" onclick="generateSaleQr()"
                    class="btn btn--mpesa" style="width:100%; font-size:1rem; padding:14px;">
                    Generate QR Code
                </button>
            </div>

            <div id="qrDisplayWrap" style="display:none; text-align:center;">
                <img id="qrImage" src="" alt="M-Pesa QR Code" style="max-width:240px; width:100%; margin:1rem auto; border-radius:8px; border:1px solid var(--color-border,#e5e7eb);">
                <p style="color:var(--color-text-muted); font-size:.85rem;">Amount: <strong id="qrAmount"></strong></p>
                <p style="color:var(--color-text-muted); font-size:.8rem;">Open M-Pesa → Lipa na M-Pesa → Scan QR</p>
            </div>

            <div id="qrStatus" style="display:none; margin-top:12px;"></div>
        </div>
        @endif

        {{-- Pesapal card payment --}}
        @if($pesapalEnabled)
        <div class="payment-tab-panel" data-panel="pesapal" @if($defaultTab !== 'pesapal') hidden @endif>
            <p class="payment-option-subtitle" style="margin-bottom:1rem;">Visa, Mastercard and Airtel Money via Pesapal.</p>
            <form method="POST" action="{{ route('pesapal.initiate') }}">
                @csrf
                <input type="hidden" name="sale_id" value="{{ $sale->id }}">
                <button type="submit" class="btn btn--pesapal" style="width:100%; padding:14px; font-size:1rem;">
                    Pay by Card via Pesapal
                </button>
            </form>
        </div>
        @endif

        {{-- Manual payment form --}}
        <div class="payment-tab-panel" data-panel="manual" @if($defaultTab !== 'manual') hidden @endif>
        <form method="POST" action="{{ route('sales.record-payment', $sale) }}">
            @csrf

            @php
                $defaultMethod = old('payment_method', match($sale->payment_method) {
                    'bank_transfer' => 'bank',
                    'mpesa'         => 'mpesa',
                    default         => 'cash',
                });
            @endphp
            <div class="form-group">
                <label class="form-label">Payment Method *</label>
                <select name="payment_method" class="form-control" id="paymentMethodSelect" onchange="togglePaymentMethod()">
                    <option value="cash"         {{ $defaultMethod === 'cash'         ? 'selected' : '' }}>Cash</option>
                    <option value="mpesa"        {{ $defaultMethod === 'mpesa'        ? 'selected' : '' }}>M-Pesa</option>
                    <option value="bank"         {{ $defaultMethod === 'bank'         ? 'selected' : '' }}>Bank Transfer</option>
                    <option value="cheque"       {{ $defaultMethod === 'cheque'       ? 'selected' : '' }}>Cheque</option>
                    <option value="store_credit" {{ $defaultMethod === 'store_credit' ? 'selected' : '' }}>Store Credit</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Amount *</label>
                <input type="number" name="amount" class="form-control"
                    step="0.01" min="0.01" max="{{ $sale->balance_due }}"
                    value="{{ old('amount', $sale->balance_due) }}"
                    style="font-size:1.1rem; font-weight:700;">
                <span class="form-hint">Max: KSh {{ number_format($sale->balance_due, 2) }}</span>
            </div>

            <div class="form-group" id="referenceGroup">
                <label class="form-label" id="referenceLabel">Reference / Transaction Code</label>
                <input type="text" name="reference" class="form-control"
                    value="{{ old('reference') }}"
                    placeholder="e.g. QHJ7X8K2, cheque #, or leave blank for cash">
            </div>

            <button type="submit" class="btn btn--primary" style="width:100%; margin-top:0.5rem; padding:14px; font-size:1rem;">
                ✓ Confirm Payment
            </button>
        </form>
        </div>

    </div>
</div>
</div>

<input type="hidden" id="saleId" value="{{ $sale->id }}">

{{-- Silent banner shown when background poll detects payment --}}
<div id="bgPayBanner" style="display:none;"></div>

@endif

@endsection

@push('scripts')
<script src="{{ asset('js/mpesa.js') }}"></script>
<script>
// Receipt URL so background poll redirects to invoice on completion
window.SALE_RECEIPT_URL = '{{ route('sales.show', $sale) }}';

// Auto-detect any incoming M-Pesa payment silently in the background
@if(!($sale->payment_status === 'paid') && $mpesaEnabled)
startBackgroundPoll({{ $sale->id }});
@endif

// Override initiateStkPush to use the amount input on this page
function initiateStkPush() {
    const phone  = document.getElementById('mpesaPhone')?.value?.trim();
    const amount = document.getElementById('mpesaAmount')?.value?.trim();
    const saleId = document.getElementById('saleId')?.value;
    const btn    = document.getElementById('stkPushBtn');
    const status = document.getElementById('mpesaStatus');

    if (!phone) { showStatus(status, 'error', 'Please enter a phone number.'); return; }
    if (!amount || parseFloat(amount) <= 0) { showStatus(status, 'error', 'Enter a valid amount.'); return; }

    btn.disabled    = true;
    btn.textContent = 'Sending…';
    showStatus(status, 'info', 'Sending STK Push to ' + phone + '…');

    fetch('/api/mpesa/initiate', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
        body: JSON.stringify({ sale_id: saleId, phone: phone }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showStatus(status, 'info', 'Request sent! Ask the customer to check their phone and enter their M-Pesa PIN.');
            btn.textContent = 'Waiting for payment…';
            pollSaleStatus(saleId, btn, status);
        } else {
            showStatus(status, 'error', data.message ?? 'Failed to send STK Push.');
            btn.disabled    = false;
            btn.textContent = 'Send STK Push to Customer';
        }
    })
    .catch(() => {
        showStatus(status, 'error', 'Network error. Please try again.');
        btn.disabled    = false;
        btn.textContent = 'Send STK Push to Customer';
    });
}

function generateSaleQr() {
    const saleId = document.getElementById('saleId')?.value;
    const btn    = document.getElementById('qrGenerateBtn');
    const status = document.getElementById('qrStatus');

    btn.disabled    = true;
    btn.textContent = 'Generating…';

    fetch('/api/mpesa/qr', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
        body: JSON.stringify({ sale_id: saleId }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('qrImage').src = 'data:image/png;base64,' + data.qr_code;
            document.getElementById('qrAmount').textContent = 'KSh ' + Number(data.amount).toLocaleString();
            document.getElementById('qrGenerateWrap').style.display = 'none';
            document.getElementById('qrDisplayWrap').style.display  = 'block';
            showStatus(status, 'info', 'Waiting for payment…');
            pollSaleStatus(saleId, btn, status);
        } else {
            showStatus(status, 'error', data.message ?? 'Failed to generate QR code.');
            btn.disabled    = false;
            btn.textContent = 'Generate QR Code';
        }
    })
    .catch(() => {
        showStatus(status, 'error', 'Network error. Please try again.');
        btn.disabled    = false;
        btn.textContent = 'Generate QR Code';
    });
}

function showPaymentTab(tab) {
    document.querySelectorAll('.payment-tab-panel').forEach(function(el) {
        el.hidden = el.dataset.panel !== tab;
    });
    document.querySelectorAll('.payment-tab-btn').forEach(function(btn) {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });
}

function togglePaymentMethod() {
    const method = document.getElementById('paymentMethodSelect').value;
    const label  = document.getElementById('referenceLabel');
    const input  = document.querySelector('#referenceGroup input[name="reference"]');
    const needsRef = (method === 'mpesa' || method === 'bank' || method === 'cheque');
    if (method === 'mpesa')  label.textContent = 'M-Pesa Transaction Code *';
    else if (method === 'bank')   label.textContent = 'Bank Reference / Transfer Code *';
    else if (method === 'cheque') label.textContent = 'Cheque Number *';
    else label.textContent = 'Reference (Optional)';
    if (input) {
        input.required = needsRef;
        input.placeholder = method === 'mpesa'  ? 'e.g. QHJ7X8K2 (from the M-Pesa message)'
                          : method === 'bank'   ? 'Bank transfer reference'
                          : method === 'cheque' ? 'Cheque number'
                          : 'Optional note or reference';
    }
}
togglePaymentMethod();

document.querySelectorAll('.alert').forEach(function(el) {
    setTimeout(() => { el.style.opacity='0'; el.style.transition='opacity .5s'; setTimeout(()=>el.remove(),500); }, 4000);
});
</script>
@endpush
