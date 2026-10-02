@extends('layouts.app')
@section('title', 'Online Orders')
@push('styles')
<style>
@media (min-width: 769px) {
    .oo-index-table th, .oo-index-table td { padding: 0.75rem 1rem; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Online Orders</h1>
            <p class="page-subtitle">Orders placed through your public online store</p>
        </div>
        @if($business?->store_slug && $business?->store_public)
        <a href="{{ route('shop.index', $business->store_slug) }}" target="_blank" class="btn btn-outline">View Live Store →</a>
        @endif
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif

    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;">
        @php
            $statusTabs = [
                ''            => 'All ('.$counts['all'].')',
                'pending'     => 'Pending ('.$counts['pending'].')',
                'paid'        => 'Paid ('.$counts['paid'].')',
                'processing'  => 'Processing ('.$counts['processing'].')',
                'shipped'     => 'Shipped ('.$counts['shipped'].')',
                'delivered'   => 'Delivered ('.$counts['delivered'].')',
                'cancelled'   => 'Cancelled ('.$counts['cancelled'].')',
            ];
        @endphp
        @foreach($statusTabs as $value => $label)
        <a href="{{ route('online-orders.index', $value ? ['status' => $value] : []) }}"
           class="btn {{ request('status', '') === $value ? 'btn-primary' : 'btn-outline' }}"
           style="font-size:0.8rem;padding:0.4rem 0.9rem;">{{ $label }}</a>
        @endforeach
    </div>

    <form method="GET" style="margin-bottom:1rem;max-width:320px;">
        @if(request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
        <input type="text" name="search" class="form-control" placeholder="Search reference, name, phone…" value="{{ request('search') }}">
    </form>

    <div class="table-card">
        @if($orders->isEmpty())
            <div style="padding:3rem;text-align:center;color:var(--color-text-muted);">No online orders {{ request('status') ? 'with this status' : 'yet' }}.</div>
        @else
        <table class="oo-index-table" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:var(--color-surface);border-bottom:2px solid var(--color-border);">
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Reference</th>
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Customer</th>
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Items</th>
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Total</th>
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Status</th>
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Placed</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($orders as $order)
                <tr style="border-bottom:1px solid var(--color-border);">
                    <td data-label="Reference" style="font-weight:700;">{{ $order->reference }}</td>
                    <td data-label="Customer">
                        {{ $order->customer_name }}
                        <div style="font-size:0.8rem;color:var(--color-text-muted);">{{ $order->customer_phone }}</div>
                    </td>
                    <td data-label="Items">{{ $order->items_count }}</td>
                    <td data-label="Total" style="font-weight:600;">KSh {{ number_format($order->total, 2) }}</td>
                    <td data-label="Status">
                        @include('online-orders.partials.status-badge', ['status' => $order->status])
                    </td>
                    <td data-label="Placed" style="font-size:0.85rem;">{{ $order->created_at->format('d M Y H:i') }}</td>
                    <td data-label="">
                        <a href="{{ route('online-orders.show', $order) }}" class="btn btn-secondary" style="font-size:0.8rem;padding:0.25rem 0.75rem;">View</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($orders->hasPages())
        <div style="padding:1rem 1.25rem;">{{ $orders->links() }}</div>
        @endif
        @endif
    </div>
</div>
@endsection
