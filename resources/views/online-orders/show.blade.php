@extends('layouts.app')
@section('title', 'Order ' . $order->reference)
@push('styles')
<style>
@media (min-width: 769px) {
    .oo-show-table th, .oo-show-table td { padding: 0.5rem; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Order {{ $order->reference }}</h1>
            <p class="page-subtitle">Placed {{ $order->created_at->format('d M Y H:i') }}</p>
        </div>
        <a href="{{ route('online-orders.index') }}" class="btn btn-outline">&#8592; Back to Orders</a>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif

    <div class="sale-layout">
        <div>
            <div class="form-card" style="margin-bottom: var(--space-md);">
                <div class="form-section-title">Customer</div>
                <p style="margin:0 0 4px;"><strong>{{ $order->customer_name }}</strong></p>
                <p style="margin:0 0 4px;color:var(--color-text-muted);">{{ $order->customer_phone }}</p>
                @if($order->customer_email)
                <p style="margin:0 0 4px;color:var(--color-text-muted);">{{ $order->customer_email }}</p>
                @endif
                @if($order->delivery_address)
                <p style="margin:8px 0 0;"><strong>Delivery address:</strong><br>{{ $order->delivery_address }}</p>
                @endif
                @if($order->notes)
                <p style="margin:8px 0 0;"><strong>Notes:</strong><br>{{ $order->notes }}</p>
                @endif
                @if($order->sale)
                <p style="margin:8px 0 0;"><strong>Sale record:</strong>
                    <a href="{{ route('sales.show', $order->sale) }}">{{ $order->sale->invoice_number }}</a>
                </p>
                @endif
            </div>

            <div class="form-card">
                <div class="form-section-title">Items</div>
                <table class="oo-show-table" style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:2px solid var(--color-border);">
                            <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Item</th>
                            <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Qty</th>
                            <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Unit Price</th>
                            <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                        <tr style="border-bottom:1px solid var(--color-border);">
                            <td data-label="Item">{{ $item->product_name }}</td>
                            <td data-label="Qty" style="text-align:right;">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                            <td data-label="Unit Price" style="text-align:right;">KSh {{ number_format($item->unit_price, 2) }}</td>
                            <td data-label="Total" style="text-align:right;font-weight:600;">KSh {{ number_format($item->total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="summary-panel">
            <h3>Order Status</h3>
            <div style="margin-bottom:1rem;">@include('online-orders.partials.status-badge', ['status' => $order->status])</div>

            <div class="summary-row">
                <span>Subtotal</span>
                <span>KSh {{ number_format($order->subtotal, 2) }}</span>
            </div>
            @if($order->coupon_discount_amount > 0)
            {{-- Without this line, subtotal + delivery fee simply didn't add
            up to the total with no explanation anywhere on this page — a
            staff member had no way to tell whether that was a bug or a
            coupon, or which coupon code was even used. --}}
            <div class="summary-row">
                <span>Coupon ({{ $order->coupon_code }})</span>
                <span>-KSh {{ number_format($order->coupon_discount_amount, 2) }}</span>
            </div>
            @endif
            @if($order->delivery_fee > 0)
            <div class="summary-row">
                <span>Delivery Fee</span>
                <span>KSh {{ number_format($order->delivery_fee, 2) }}</span>
            </div>
            @endif
            <div class="summary-row total">
                <span>Total</span>
                <span>KSh {{ number_format($order->total, 2) }}</span>
            </div>

            <p style="font-size:0.85rem;color:var(--color-text-muted);margin-top:0.75rem;">
                Payment method: {{ ucfirst($order->payment_method ?? 'mpesa') }}
                @if($order->payment_confirmed_at)
                    <br>Confirmed {{ $order->payment_confirmed_at->format('d M Y H:i') }}
                @endif
            </p>

            <div class="form-actions" style="margin-top:1rem;display:flex;flex-direction:column;gap:0.5rem;">
                @if($order->status === 'pending')
                <form method="POST" action="{{ route('online-orders.mark-paid', $order) }}" onsubmit="return confirm('Confirm this order has been paid (e.g. cash or bank transfer received)? This will deduct stock.');">
                    @csrf
                    <button type="submit" class="btn btn-primary" style="width:100%;">Mark as Paid</button>
                </form>
                @endif

                @if(in_array($order->status, ['paid', 'processing', 'shipped']))
                <form method="POST" action="{{ route('online-orders.status', $order) }}">
                    @csrf
                    <div style="display:flex;gap:0.5rem;">
                        <select name="status" class="form-control" style="flex:1;">
                            <option value="processing" {{ $order->status === 'processing' ? 'selected' : '' }}>Processing</option>
                            <option value="shipped" {{ $order->status === 'shipped' ? 'selected' : '' }}>Shipped</option>
                            <option value="delivered" {{ $order->status === 'delivered' ? 'selected' : '' }}>Delivered</option>
                        </select>
                        <button type="submit" class="btn btn-secondary">Update</button>
                    </div>
                </form>
                @endif

                @if(in_array($order->status, ['pending', 'paid']))
                <form method="POST" action="{{ route('online-orders.cancel', $order) }}" onsubmit="return confirm('Cancel this order?{{ $order->status === 'paid' ? ' Stock will be restored.' : '' }}');">
                    @csrf
                    <button type="submit" class="btn btn-outline" style="width:100%;color:var(--color-danger);">Cancel Order</button>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
