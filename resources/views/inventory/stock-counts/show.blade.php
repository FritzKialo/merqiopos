@extends('layouts.app')
@section('title', 'Stock Count - ' . $count->reference)
@push('styles')
<style>
@media (min-width: 769px) {
    .stock-count-items-table th { padding: 12px 16px; }
    .stock-count-items-table td { padding: 10px 16px; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <h1>{{ $count->reference }}</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        @if($count->isEditable())
        <form method="POST" action="{{ route('inventory.stock-counts.complete', $count) }}">@csrf
            <button class="btn btn-primary">Complete &amp; Post Adjustments</button>
        </form>
        <form method="POST" action="{{ route('inventory.stock-counts.cancel', $count) }}">@csrf
            <button class="btn btn-danger" onclick="return confirm('Cancel this stock count?')">Cancel</button>
        </form>
        @endif
    </div>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

@if($count->isEditable())
<form method="POST" action="{{ route('inventory.stock-counts.update', $count) }}">
@csrf @method('PATCH')
@endif

<div class="card">
<div class="card-body" style="padding:0;">
<div class="table-wrapper">
<table class="stock-count-items-table" style="width:100%;border-collapse:collapse;">
<thead><tr style="border-bottom:1px solid var(--color-border);background:var(--color-surface-2);">
    <th style="text-align:left;">Product</th>
    <th style="text-align:right;">System Qty</th>
    <th style="text-align:right;">Counted Qty</th>
    <th style="text-align:right;">Variance</th>
</tr></thead>
<tbody>
@foreach($count->items as $item)
@php $variance = $item->variance; @endphp
<tr style="border-bottom:1px solid var(--color-border);">
    <td data-label="Product">{{ $item->product->name }}</td>
    <td data-label="System Qty" style="text-align:right;">{{ $item->system_qty }}</td>
    <td data-label="Counted Qty" style="text-align:right;">
        @if($count->isEditable())
        <input type="number" name="items[{{ $item->id }}]" class="form-control" style="width:100px;display:inline-block;text-align:right;" step="0.01" value="{{ $item->counted_qty }}" placeholder="0">
        @else
        {{ $item->counted_qty ?? '—' }}
        @endif
    </td>
    <td data-label="Variance" style="text-align:right;">
        @if($variance !== null)
            <span class="{{ $variance > 0 ? 'text-success' : ($variance < 0 ? 'text-danger' : 'text-muted') }}" style="font-weight:600;">
                {{ $variance > 0 ? '+' : '' }}{{ $variance }}
            </span>
        @else
            <span class="text-muted">—</span>
        @endif
    </td>
</tr>
@endforeach
</tbody>
</table>
</div>
</div>
</div>

@if($count->isEditable())
<div style="margin-top:16px;display:flex;gap:12px;">
<button type="submit" class="btn btn-secondary">Save Progress</button>
</div>
</form>
@endif
@endsection
