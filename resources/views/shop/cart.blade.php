@extends('shop.layout')
@section('title', 'Your Cart — ' . $business->name)
{{-- A shopping cart is per-session, empty-by-default, thin-content —
nothing here is worth a search result, and indexing it just competes
with the actual product pages for crawl budget. --}}
@push('robots')<meta name="robots" content="noindex, follow">@endpush

@section('content')
<h1 class="cart-title">Your Cart</h1>

@if($couponInvalidMessage)
<div class="alert alert-error">{{ $couponInvalidMessage }}</div>
@endif

@if(empty($cart))
<div class="empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
    <p>Your cart is empty. <a href="{{ route('shop.index', $business->store_slug) }}" style="color:var(--shop-accent);font-weight:700;">Continue shopping</a></p>
</div>
@else
<div class="cart-table-wrap">
<table class="cart-table">
    <thead>
        <tr>
            <th>Product</th>
            <th>Unit Price</th>
            <th>Qty</th>
            <th>Total</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @php $subtotal = 0; @endphp
        @foreach($cart as $key => $item)
        @php $rowTotal = $item['price'] * $item['qty']; $subtotal += $rowTotal; @endphp
        <tr>
            <td data-label="Product">{{ $item['name'] }}@if(!empty($item['variant_name'])) <span style="color:var(--shop-ink-soft);font-size:0.85rem;"> / {{ $item['variant_name'] }}</span>@endif</td>
            <td data-label="Unit Price">KSh {{ number_format($item['price'], 0) }}</td>
            <td data-label="Qty">
                <form method="POST" action="{{ route('shop.cart.update-qty', $business->store_slug) }}" class="qty-form">
                    @csrf
                    <input type="hidden" name="key" value="{{ $key }}">
                    <input type="number" name="qty" value="{{ $item['qty'] }}" min="1">
                    <button type="submit" class="qty-update-btn">Update</button>
                </form>
            </td>
            <td data-label="Total">KSh {{ number_format($rowTotal, 0) }}</td>
            <td data-label="Actions">
                <form method="POST" action="{{ route('shop.cart.remove', [$business->store_slug, $key]) }}" onsubmit="return confirm('Remove {{ addslashes($item['name']) }} from your cart?')">
                    @csrf
                    <button type="submit" class="cart-remove-btn" aria-label="Remove">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                    </button>
                </form>
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="cart-total-row">
            <td colspan="4" style="text-align:right;">Subtotal</td>
            <td data-label="Subtotal">KSh {{ number_format($subtotal, 0) }}</td>
        </tr>
        <tr class="cart-total-row" id="couponRow" style="display:{{ $appliedCoupon ? 'table-row' : 'none' }};">
            <td colspan="4" style="text-align:right;font-size:0.9rem;font-weight:600;color:var(--shop-success,#16a34a);"><span id="couponLabel">Coupon ({{ $appliedCoupon['code'] ?? '' }})</span></td>
            <td data-label="Coupon" id="couponDiscountCell" style="color:var(--shop-success,#16a34a);">-KSh {{ number_format($appliedCoupon['discount'] ?? 0, 0) }}</td>
        </tr>
        <tr class="cart-total-row" id="deliveryFeeRow" style="display:none;">
            <td colspan="4" style="text-align:right;font-size:0.9rem;font-weight:600;">Delivery Fee</td>
            <td data-label="Delivery Fee" id="deliveryFeeCell">KSh 0</td>
        </tr>
        <tr class="cart-total-row grand">
            <td colspan="4" style="text-align:right;">Total</td>
            <td data-label="Total" id="grandTotalCell">KSh {{ number_format($subtotal, 0) }}</td>
        </tr>
    </tfoot>
</table>
</div>

<div class="section-title" style="margin-top:0;">Coupon Code</div>
<div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:24px; align-items:flex-start;">
    <div style="flex:1; min-width:180px;">
        <input type="text" id="couponCodeInput" class="form-control" placeholder="Enter coupon code"
            value="{{ $appliedCoupon['code'] ?? '' }}" {{ $appliedCoupon ? 'disabled' : '' }} style="text-transform:uppercase;">
        <div id="couponMsg" style="font-size:0.82rem; margin-top:6px;"></div>
    </div>
    <button type="button" class="btn btn-outline" id="couponApplyBtn" style="{{ $appliedCoupon ? 'display:none;' : '' }}">Apply</button>
    <button type="button" class="btn btn-outline" id="couponRemoveBtn" style="{{ $appliedCoupon ? '' : 'display:none;' }} color:var(--shop-danger,#dc2626);">Remove</button>
</div>

@php
    $hasMpesa = $business->hasMpesaConfigured();
    $flatFee  = (float) ($business->delivery_fee ?? 0);
    $initialCouponDiscount = (float) ($appliedCoupon['discount'] ?? 0);
@endphp

<div class="section-title">Your Details &amp; Payment</div>
<form method="POST" action="{{ route('shop.checkout', $business->store_slug) }}" class="checkout-form" id="checkoutForm">
    @csrf
    <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="customer_name" class="form-control" required value="{{ old('customer_name') }}">
    </div>
    <div class="form-group">
        <label>Phone Number (M-Pesa) *</label>
        <input type="tel" name="customer_phone" class="form-control" required placeholder="07XXXXXXXX" value="{{ old('customer_phone') }}">
    </div>
    <div class="form-group">
        <label>Email (Optional)</label>
        <input type="email" name="customer_email" class="form-control" value="{{ old('customer_email') }}">
    </div>

    @if(count($deliveryZones))
    <div class="form-group">
        <label>Delivery</label>
        <select name="delivery_zone" id="deliveryZone" class="form-control">
            <option value="">Pickup — no delivery (Free)</option>
            @foreach($deliveryZones as $zone)
            <option value="{{ $zone['name'] }}" data-fee="{{ $zone['fee'] }}" {{ old('delivery_zone') === $zone['name'] ? 'selected' : '' }}>
                {{ $zone['name'] }} — KSh {{ number_format($zone['fee'], 0) }}
            </option>
            @endforeach
        </select>
    </div>
    @else
    <div class="form-group">
        <label class="checkbox-row">
            <input type="checkbox" id="wantDelivery" {{ old('delivery_address') ? 'checked' : '' }}>
            <span>I need delivery{{ $flatFee > 0 ? ' (+KSh ' . number_format($flatFee, 0) . ')' : '' }}</span>
        </label>
    </div>
    @endif

    <div class="form-group" id="deliveryAddressGroup" style="{{ old('delivery_address') || old('delivery_zone') ? '' : 'display:none;' }}">
        <label>Delivery Address {{ count($deliveryZones) ? '(exact location within zone)' : '' }}</label>
        <textarea name="delivery_address" id="deliveryAddress" class="form-control" rows="2">{{ old('delivery_address') }}</textarea>
    </div>

    <div class="form-group">
        <label>Notes (Optional)</label>
        <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
    </div>

    <div class="form-group">
        <label>Payment</label>
        @if($hasMpesa)
        <label class="radio-row">
            <input type="radio" name="payment_method" value="mpesa" checked>
            <span>Pay Now via M-Pesa</span>
        </label>
        <label class="radio-row">
            <input type="radio" name="payment_method" value="cash">
            <span>Pay on Delivery (Cash)</span>
        </label>
        @else
        {{-- No point offering an M-Pesa option nobody can actually complete
        — the business hasn't configured it, so the push would never fire
        and the order would sit "pending" forever with no way to confirm. --}}
        <input type="hidden" name="payment_method" value="cash">
        <p style="color:var(--shop-ink-soft);font-size:0.9rem;margin:0;">Cash on Delivery — pay when your order arrives.</p>
        @endif
    </div>

    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:8px;">
        <button type="submit" class="btn btn-dark" id="checkoutSubmit">Pay KSh {{ number_format($subtotal, 0) }} via M-Pesa</button>
        <a href="{{ route('shop.index', $business->store_slug) }}" class="btn btn-outline">Continue Shopping</a>
    </div>
</form>

<script>
(function() {
    var subtotal   = {{ (float) $subtotal }};
    var flatFee    = {{ $flatFee }};
    var hasZones   = {{ count($deliveryZones) ? 'true' : 'false' }};
    var hasMpesa   = {{ $hasMpesa ? 'true' : 'false' }};
    var couponDiscount = {{ $initialCouponDiscount }};
    var zoneSelect = document.getElementById('deliveryZone');
    var wantCheck  = document.getElementById('wantDelivery');
    var addrGroup  = document.getElementById('deliveryAddressGroup');
    var feeRow     = document.getElementById('deliveryFeeRow');
    var feeCell    = document.getElementById('deliveryFeeCell');
    var couponRow  = document.getElementById('couponRow');
    var couponCell = document.getElementById('couponDiscountCell');
    var totalCell  = document.getElementById('grandTotalCell');
    var submitBtn  = document.getElementById('checkoutSubmit');

    function currentFee() {
        if (hasZones) {
            if (!zoneSelect || !zoneSelect.value) return 0;
            var opt = zoneSelect.options[zoneSelect.selectedIndex];
            return parseFloat(opt.getAttribute('data-fee') || '0');
        }
        return (wantCheck && wantCheck.checked) ? flatFee : 0;
    }

    function paymentMethod() {
        if (!hasMpesa) return 'cash';
        var checked = document.querySelector('input[name="payment_method"]:checked');
        return checked ? checked.value : 'mpesa';
    }

    function update() {
        var fee   = currentFee();
        var total = Math.max(0, subtotal - couponDiscount) + fee;

        if (fee > 0) {
            feeRow.style.display = '';
            feeCell.textContent = 'KSh ' + fee.toLocaleString();
        } else {
            feeRow.style.display = 'none';
        }
        if (couponDiscount > 0) {
            couponRow.style.display = 'table-row';
            couponCell.textContent = '-KSh ' + couponDiscount.toLocaleString();
        } else {
            couponRow.style.display = 'none';
        }
        totalCell.textContent = 'KSh ' + total.toLocaleString();

        var method = paymentMethod();
        submitBtn.textContent = method === 'cash'
            ? 'Place Order — Pay KSh ' + total.toLocaleString() + ' on Delivery'
            : 'Pay KSh ' + total.toLocaleString() + ' via M-Pesa';

        if (hasZones) {
            addrGroup.style.display = (zoneSelect && zoneSelect.value) ? '' : 'none';
        } else {
            addrGroup.style.display = (wantCheck && wantCheck.checked) ? '' : 'none';
        }
    }

    if (zoneSelect) zoneSelect.addEventListener('change', update);
    if (wantCheck) wantCheck.addEventListener('change', update);
    document.querySelectorAll('input[name="payment_method"]').forEach(function(r) {
        r.addEventListener('change', update);
    });

    // ── Coupon apply/remove — AJAX so the total updates live without a
    // full page reload, matching the delivery-zone experience above. The
    // discount itself is re-validated server-side again at checkout
    // submission — this endpoint is just for immediate feedback.
    var couponInput  = document.getElementById('couponCodeInput');
    var couponMsg    = document.getElementById('couponMsg');
    var applyBtn     = document.getElementById('couponApplyBtn');
    var removeBtn    = document.getElementById('couponRemoveBtn');
    var csrfToken    = document.querySelector('input[name="_token"]').value;

    applyBtn.addEventListener('click', function () {
        var code = couponInput.value.trim();
        if (!code) return;
        applyBtn.disabled = true;
        applyBtn.textContent = 'Checking…';
        couponMsg.textContent = '';

        fetch('{{ route("shop.coupon.apply", $business->store_slug) }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ code: code })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            applyBtn.disabled = false;
            applyBtn.textContent = 'Apply';
            couponMsg.style.color = data.valid ? 'var(--shop-success,#16a34a)' : 'var(--shop-danger,#dc2626)';
            couponMsg.textContent = data.message;
            if (data.valid) {
                couponDiscount = parseFloat(data.discount) || 0;
                var lbl = document.getElementById('couponLabel');
                if (lbl) lbl.textContent = 'Coupon (' + (data.code || couponInput.value).toUpperCase().trim() + ')';
                couponInput.disabled = true;
                applyBtn.style.display = 'none';
                removeBtn.style.display = '';
                update();
            }
        })
        .catch(function () {
            applyBtn.disabled = false;
            applyBtn.textContent = 'Apply';
            couponMsg.style.color = 'var(--shop-danger,#dc2626)';
            couponMsg.textContent = 'Could not check that code — please try again.';
        });
    });

    removeBtn.addEventListener('click', function () {
        removeBtn.disabled = true;
        fetch('{{ route("shop.coupon.remove", $business->store_slug) }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        }).finally(function () {
            couponDiscount = 0;
            couponInput.value = '';
            couponInput.disabled = false;
            couponMsg.textContent = '';
            applyBtn.style.display = '';
            removeBtn.style.display = 'none';
            removeBtn.disabled = false;
            update();
        });
    });

    update();
})();
</script>
@endif
@endsection
