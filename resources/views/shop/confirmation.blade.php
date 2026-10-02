@extends('shop.layout')
@section('title', 'Order Confirmation — ' . $business->name)
{{-- A customer's own order confirmation (name, phone, items ordered) has
no business being indexed at all — this was previously fully crawlable
and searchable by anyone, which is both a privacy concern and pointless
duplicate/thin content from an SEO standpoint. --}}
@push('robots')<meta name="robots" content="noindex, nofollow">@endpush

@section('content')
<div class="confirm-wrap">
    @if($order->status === 'paid')
    <div class="confirm-icon success"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
    <h1 class="confirm-title">Payment Confirmed!</h1>
    <p class="confirm-text">Thank you, {{ $order->customer_name }}. Your order <strong>#{{ $order->reference }}</strong> has been received.</p>
    @elseif($order->status === 'pending')
        @if($order->payment_method === 'cash')
        {{-- Cash on Delivery — the order itself IS confirmed/accepted even
        though no money has changed hands yet, which is a different meaning
        of "pending" than an M-Pesa push still awaiting a PIN. Staff mark it
        paid (via the existing Mark Paid action) once cash is collected. --}}
        <div class="confirm-icon success"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
        <h1 class="confirm-title">Order Confirmed</h1>
        <p class="confirm-text">Thank you, {{ $order->customer_name }}. Your order <strong>#{{ $order->reference }}</strong> is confirmed. Please have <strong>KSh {{ number_format($order->total, 0) }}</strong> ready to pay on delivery.</p>
        @elseif($order->mpesa_checkout_id)
        {{-- Genuinely still pending — don't say "Received" here, that reads
        as done when payment hasn't actually been confirmed yet. --}}
        <div class="confirm-icon pending"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
        <h1 class="confirm-title">Awaiting Payment Confirmation</h1>
        <p class="confirm-text">An M-Pesa STK push was sent to <strong>{{ $order->customer_phone }}</strong>. Please complete the payment prompt on your phone.</p>
        <button onclick="window.location.reload()" class="btn btn-outline" style="margin-bottom:24px;">Refresh Status</button>
        @include('shop.mpesa-qr-block')
        @else
        {{-- Previously this always claimed an STK push was sent, even when the
        business has no M-Pesa configured (or the push failed to send) — the
        customer would be told to check their phone for a prompt that never
        arrived. Only shown above when mpesa_checkout_id is actually set. --}}
        <div class="confirm-icon pending"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
        <h1 class="confirm-title">Awaiting Payment Confirmation</h1>
        <p class="confirm-text">Thank you, {{ $order->customer_name }}. We've received your order and will contact you shortly to arrange payment.</p>
        @endif
    <p class="confirm-ref">Order: <strong>#{{ $order->reference }}</strong></p>
    @else
    <div class="confirm-icon error"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
    <h1 class="confirm-title">Order {{ ucfirst($order->status) }}</h1>
    <p class="confirm-text">Order reference: <strong>#{{ $order->reference }}</strong></p>
    @endif

    <div class="order-summary">
        <span class="order-summary-title">Order Summary</span>
        @foreach($order->items as $item)
        {{-- A $0-unit_price line only ever exists as a bundle's real
        component row, expanded purely so stock decrements correctly
        per-component — the bundle's own summary line right above already
        shows the real name/price/qty the customer paid for. --}}
        @continue($item->unit_price == 0)
        <div class="order-summary-row">
            <span>{{ $item->product_name }} × {{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</span>
            <span>KSh {{ number_format($item->total, 0) }}</span>
        </div>
        @endforeach
        @if($order->coupon_discount_amount > 0)
        <div class="order-summary-row">
            <span>Coupon ({{ $order->coupon_code }})</span>
            <span>-KSh {{ number_format($order->coupon_discount_amount, 0) }}</span>
        </div>
        @endif
        @if($order->delivery_fee > 0)
        <div class="order-summary-row">
            <span>Delivery Fee</span>
            <span>KSh {{ number_format($order->delivery_fee, 0) }}</span>
        </div>
        @endif
        <div class="order-summary-row total">
            <span>Total</span>
            <span>KSh {{ number_format($order->total, 0) }}</span>
        </div>
    </div>

    <a href="{{ route('shop.index', $business->store_slug) }}" class="btn btn-dark">Continue Shopping</a>
</div>
@endsection
