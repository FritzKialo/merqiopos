@extends('layouts.app')
@section('title', 'Bin Locations — ' . $warehouse->name)
@push('styles')
<style>
@media (min-width: 769px) {
    .bin-items-table td { padding: 6px 4px; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <div>
        <a href="{{ route('warehouses.show', $warehouse) }}" class="text-muted" style="text-decoration:none;font-size:0.88rem;">← {{ $warehouse->name }}</a>
        <h1 style="margin:4px 0 0;">Bin Locations</h1>
    </div>
    <a href="{{ route('warehouses.show', $warehouse) }}" class="btn btn-secondary">View All Stock</a>
</div>

@if(session('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif

@if($bins->isEmpty())
<div class="text-muted" style="text-align:center;padding:60px;">
    <div style="margin-bottom:12px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:32px;height:32px;"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></div>
    <p>No bin locations assigned yet.</p>
    <p style="font-size:0.88rem;">Go to <a href="{{ route('warehouses.show', $warehouse) }}">warehouse stock</a> and set a bin code (e.g. A1, B3, Shelf-2) on any item.</p>
</div>
@else
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
@foreach($bins as $binCode => $items)
<div class="card">
<div class="card-body">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h3 style="margin:0;font-size:1.1rem;font-family:monospace;background:var(--color-surface-2);padding:4px 10px;border-radius:4px;">{{ $binCode }}</h3>
        <span class="text-muted" style="font-size:0.82rem;">{{ $items->count() }} SKU{{ $items->count()!==1?'s':'' }}</span>
    </div>
    <table class="bin-items-table" style="width:100%;border-collapse:collapse;font-size:0.88rem;">
    @foreach($items as $stock)
    <tr style="border-bottom:1px solid var(--color-border);">
        <td data-label="Product">{{ $stock->product->name ?? '—' }}</td>
        <td data-label="Qty" style="text-align:right;font-weight:600;">{{ number_format($stock->quantity, 0) }}</td>
        <td data-label="Actions" style="text-align:right;">
            <button onclick="openMoveModal({{ $stock->id }}, '{{ addslashes($stock->product->name ?? '') }}', '{{ addslashes($binCode) }}')" style="background:none;border:none;color:var(--color-primary);cursor:pointer;font-size:0.78rem;text-decoration:underline;">Move</button>
        </td>
    </tr>
    @endforeach
    </table>
    <form method="POST" action="{{ route('warehouses.bins.clear', $warehouse) }}" style="margin-top:10px;" onsubmit="return confirm('Clear all bin assignments for {{ addslashes($binCode) }}?')">
        @csrf
        <input type="hidden" name="bin_location" value="{{ $binCode }}">
        <button type="submit" class="text-muted" style="font-size:0.78rem;background:none;border:1px solid var(--color-border);padding:3px 8px;border-radius:4px;cursor:pointer;width:100%;">Clear bin assignments</button>
    </form>
</div>
</div>
@endforeach
</div>
@endif

{{-- Move to bin modal --}}
<div id="move-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
<div style="background:var(--color-surface);border-radius:8px;padding:24px;width:360px;max-width:90vw;">
    <h3 style="margin:0 0 16px;" id="move-modal-title">Move Item</h3>
    <form method="POST" action="{{ route('warehouses.bins.move', $warehouse) }}">
        @csrf
        <input type="hidden" name="stock_id" id="move-stock-id">
        <div style="margin-bottom:16px;">
            <label class="form-label">New Bin Location</label>
            <input type="text" name="new_bin_location" id="move-bin-input" class="form-control" placeholder="e.g. A1, B3, Shelf-2" required style="text-transform:uppercase;">
            <p class="text-muted" style="font-size:0.78rem;margin:4px 0 0;">Leave empty to remove from a bin — use the Clear button instead.</p>
        </div>
        <div style="display:flex;gap:8px;">
            <button type="submit" class="btn btn-primary">Move</button>
            <button type="button" onclick="closeMoveModal()" class="btn btn-secondary">Cancel</button>
        </div>
    </form>
</div>
</div>

<script>
function openMoveModal(stockId, productName, currentBin) {
    document.getElementById('move-stock-id').value = stockId;
    document.getElementById('move-modal-title').textContent = 'Move: ' + productName;
    document.getElementById('move-bin-input').value = '';
    document.getElementById('move-bin-input').placeholder = 'Current: ' + currentBin;
    document.getElementById('move-modal').style.display = 'flex';
}
function closeMoveModal() {
    document.getElementById('move-modal').style.display = 'none';
}
document.getElementById('move-modal').addEventListener('click', function(e) {
    if (e.target === this) closeMoveModal();
});
document.getElementById('move-bin-input').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});
</script>
@endsection
