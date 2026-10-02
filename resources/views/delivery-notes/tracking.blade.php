@extends('layouts.app')
@section('title', 'Delivery Tracking — ' . $deliveryNote->delivery_number)
@push('styles')
<style>
@media (max-width: 700px) {
    .dn-tracking-layout { grid-template-columns: 1fr !important; }
}
@media (min-width: 769px) {
    .dn-tracking-items-table th, .dn-tracking-items-table td { padding: 8px 12px; }
}
</style>
@endpush
@section('content')
<div class="page-header">
    <h1>Delivery {{ $deliveryNote->delivery_number }}</h1>
    <a href="{{ route('delivery-notes.index') }}" class="btn btn-secondary">← Back</a>
</div>

<div class="dn-tracking-layout" style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
    <div class="card">
        <div class="card-body">
            <h3 style="margin:0 0 16px;">Delivery Details</h3>
            <table class="table-plain" style="width:100%; font-size:0.9rem;">
                <tr><td class="text-muted" style="padding:5px 0;">Delivery #</td><td style="text-align:right;">{{ $deliveryNote->delivery_number }}</td></tr>
                <tr><td class="text-muted" style="padding:5px 0;">Customer</td><td style="text-align:right;">{{ optional($deliveryNote->customer)->name ?? 'Walk-in' }}</td></tr>
                <tr><td class="text-muted" style="padding:5px 0;">Delivery Address</td><td style="text-align:right;">{{ $deliveryNote->delivery_address ?? '—' }}</td></tr>
                <tr><td class="text-muted" style="padding:5px 0;">Expected Date</td><td style="text-align:right;">{{ $deliveryNote->expected_date ? \Carbon\Carbon::parse($deliveryNote->expected_date)->format('d M Y') : '—' }}</td></tr>
                <tr><td class="text-muted" style="padding:5px 0;">Driver</td><td style="text-align:right;">{{ $deliveryNote->driver_name ?? '—' }}</td></tr>
                <tr><td class="text-muted" style="padding:5px 0;">Vehicle</td><td style="text-align:right;">{{ $deliveryNote->vehicle_plate ?? '—' }}</td></tr>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h3 style="margin:0 0 16px;">Update Status</h3>
            <form method="POST" action="{{ route('delivery-notes.status', $deliveryNote) }}">
                @csrf @method('PATCH')
                <div style="margin-bottom:12px;">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        @foreach(['draft','dispatched','in_transit','delivered','failed'] as $s)
                        <option value="{{ $s }}" {{ $deliveryNote->status === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="margin-bottom:16px;">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2">{{ $deliveryNote->notes }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary">Update</button>
            </form>
        </div>
    </div>
</div>

<div class="card" style="margin-top:24px;">
    <div class="card-body">
        <h3 style="margin:0 0 16px;">Items</h3>
        <table class="dn-tracking-items-table" style="width:100%; border-collapse:collapse;">
            <thead><tr style="border-bottom:2px solid var(--color-text);">
                <th style="text-align:left;">Product</th>
                <th style="text-align:right;">Ordered</th>
                <th style="text-align:right;">Delivered</th>
            </tr></thead>
            <tbody>
                @foreach($deliveryNote->items as $item)
                <tr style="border-bottom:1px solid var(--color-border);">
                    <td data-label="Product">{{ $item->product_name }}</td>
                    <td data-label="Ordered" style="text-align:right;">{{ $item->quantity_ordered }}</td>
                    <td data-label="Delivered" style="text-align:right;">{{ $item->quantity_delivered ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
