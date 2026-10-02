@extends('layouts.app')
@section('title', $warehouse->name . ' — Stock')
@push('styles')
<style>
@media (max-width: 900px) {
    .wh-update-stock-grid { grid-template-columns: 1fr 1fr !important; }
}
@media (max-width: 500px) {
    .wh-update-stock-grid { grid-template-columns: 1fr !important; }
}
@media (min-width: 769px) {
    .wh-stock-table th, .wh-stock-table td { padding: 10px 14px; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <div>
        <h1>{{ $warehouse->name }}</h1>
        @if($warehouse->location)<p class="text-muted" style="margin:0;">{{ $warehouse->location }}</p>@endif
    </div>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('warehouses.bins', $warehouse) }}" class="btn btn-secondary">Bin Locations</a>
        <a href="{{ route('warehouses.index') }}" class="btn btn-secondary">← Back</a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
@endif

{{-- Add / Update Stock Form --}}
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <h3 style="margin:0 0 16px;">Update Stock</h3>
        <form method="POST" action="{{ route('warehouses.stock', $warehouse) }}" class="wh-update-stock-grid" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:12px;align-items:end;">
            @csrf
            <div>
                <label class="form-label">Product</label>
                <select name="product_id" class="form-control" required>
                    <option value="">Select product...</option>
                    @foreach($products as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}{{ $p->sku ? ' ('.$p->sku.')' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Quantity</label>
                <input type="number" name="quantity" class="form-control" min="0" step="0.01" placeholder="0" required>
            </div>
            <div>
                <label class="form-label">Bin / Shelf</label>
                <input type="text" name="bin_location" class="form-control" placeholder="e.g. A3, Shelf-B">
            </div>
            <button type="submit" class="btn btn-primary">Update</button>
        </form>
    </div>
</div>

{{-- Stock Table --}}
<div class="card">
    <div class="card-body" style="padding:0;">
        <table class="wh-stock-table" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:1px solid var(--color-border);">
                    <th style="text-align:left;">Product</th>
                    <th style="text-align:left;">SKU</th>
                    <th style="text-align:left;">Bin / Shelf</th>
                    <th style="text-align:right;">Quantity</th>
                </tr>
            </thead>
            <tbody>
            @forelse($stock as $s)
            <tr style="border-bottom:1px solid var(--color-border);">
                <td data-label="Product">{{ $s->product?->name ?? 'N/A' }}</td>
                <td data-label="SKU" class="text-muted" style="font-size:0.85rem;">{{ $s->product?->sku ?? '—' }}</td>
                <td data-label="Bin / Shelf" style="font-size:0.85rem;">{{ $s->bin_location ?? '—' }}</td>
                <td data-label="Quantity" style="text-align:right;font-weight:600;">{{ number_format($s->quantity, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-muted" style="padding:40px;text-align:center;">No stock recorded for this warehouse yet.</td></tr>
            @endforelse
            </tbody>
            @if($stock->isNotEmpty())
            <tfoot>
                <tr style="border-top:2px solid var(--color-border);background:var(--color-surface-2);">
                    <td data-label="Total Units" colspan="3" style="font-weight:600;">Total Units</td>
                    <td data-label="Quantity" style="text-align:right;font-weight:700;">{{ number_format($stock->sum('quantity'), 2) }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
