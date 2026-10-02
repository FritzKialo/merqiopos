@extends('layouts.app')
@section('title', 'Supplier Credit Note')
@push('styles')
<style>
@media (max-width: 600px) {
    .scn-show-header-grid { grid-template-columns: 1fr !important; }
}
@media (min-width: 769px) {
    .scn-show-table th, .scn-show-table td { padding: 8px; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <h1>{{ $supplierCreditNote->credit_number }}</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        @if($supplierCreditNote->status === 'pending')
        <a href="{{ route('supplier-credit-notes.edit', $supplierCreditNote) }}" class="btn btn-secondary">Edit</a>
        <form method="POST" action="{{ route('supplier-credit-notes.apply', $supplierCreditNote) }}" style="display:inline;">
            @csrf
            <button class="btn btn-primary" onclick="return confirm('Mark this credit note as applied?')">Apply Credit</button>
        </form>
        <form method="POST" action="{{ route('supplier-credit-notes.destroy', $supplierCreditNote) }}" style="display:inline;" onsubmit="return confirm('Delete this credit note?')">
            @csrf @method('DELETE')
            <button class="btn btn-danger">Delete</button>
        </form>
        @endif
        <a href="{{ route('supplier-credit-notes.index') }}" class="btn btn-secondary">Back</a>
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div style="max-width:900px;">
<div class="card">
<div class="card-body">
<div class="scn-show-header-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
<div>
    @if($supplierCreditNote->supplier)
    <strong>Supplier:</strong> {{ $supplierCreditNote->supplier->name }}<br>
    @if($supplierCreditNote->supplier->phone)<span class="text-muted" style="font-size:0.9rem;">{{ $supplierCreditNote->supplier->phone }}</span>@endif
    @else
    <span class="text-muted">No supplier linked</span>
    @endif
</div>
<div style="text-align:right;">
    <div style="font-size:1.4rem;font-weight:700;font-family:monospace;">{{ $supplierCreditNote->credit_number }}</div>
    <div class="text-muted">Date: {{ $supplierCreditNote->issue_date?->format('d M Y') }}</div>
    <div class="text-muted">Reason: {{ ucfirst($supplierCreditNote->reason) }}</div>
    <div style="margin-top:6px;">
        @if($supplierCreditNote->status === 'pending')<span class="badge badge-warning">Pending</span>
        @elseif($supplierCreditNote->status === 'applied')<span class="badge badge-success">Applied</span>
        @elseif($supplierCreditNote->status === 'cancelled')<span class="badge badge-danger">Cancelled</span>
        @else<span class="badge badge-secondary">{{ ucfirst($supplierCreditNote->status) }}</span>@endif
    </div>
</div>
</div>

<div class="table-wrapper">
<table class="scn-show-table" style="width:100%;border-collapse:collapse;margin-bottom:24px;">
<thead><tr style="border-bottom:2px solid var(--color-text);">
    <th style="text-align:left;">Description</th>
    <th style="text-align:right;">Qty</th>
    <th style="text-align:right;">Unit Price</th>
    <th style="text-align:right;">Total</th>
</tr></thead>
<tbody>
@foreach($supplierCreditNote->items as $item)
<tr style="border-bottom:1px solid var(--color-border);">
    <td data-label="Description">{{ $item->description }}</td>
    <td data-label="Qty" style="text-align:right;">{{ $item->quantity }}</td>
    <td data-label="Unit Price" style="text-align:right;">KSh {{ number_format($item->unit_price, 2) }}</td>
    <td data-label="Total" style="text-align:right;">KSh {{ number_format($item->total, 2) }}</td>
</tr>
@endforeach
</tbody>
<tfoot>
<tr style="font-weight:700;font-size:1.1rem;border-top:2px solid var(--color-text);">
    <td colspan="3" data-label="Total Credit" style="text-align:right;">Total Credit</td>
    <td data-label="Amount" style="text-align:right;">KSh {{ number_format($supplierCreditNote->amount, 2) }}</td>
</tr>
</tfoot>
</table>
</div>

@if($supplierCreditNote->notes)
<div style="padding:12px;background:var(--color-surface-2);border-radius:4px;font-size:0.9rem;">
<strong>Notes:</strong> {{ $supplierCreditNote->notes }}
</div>
@endif
</div>
</div>
</div>

<div style="max-width:900px;">
<div class="card">
<div class="card-body">
    @include('partials.attachments', ['modelType' => 'SupplierCreditNote', 'modelId' => $supplierCreditNote->id])
</div>
</div>
</div>
@endsection
