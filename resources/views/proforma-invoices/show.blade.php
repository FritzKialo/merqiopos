@extends('layouts.app')
@section('title', 'Proforma Invoice')
@push('styles')
<style>
@media (min-width: 769px) {
    .pf-show-table th, .pf-show-table td { padding: 8px; }
}
@media (max-width: 700px) {
    .pf-show-header-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <h1>{{ $proformaInvoice->proforma_number }}</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        @if($proformaInvoice->status === 'draft')
        <a href="{{ route('proforma-invoices.edit', $proformaInvoice) }}" class="btn btn-secondary">Edit</a>
        <form method="POST" action="{{ route('proforma-invoices.send', $proformaInvoice) }}" style="display:inline;">
            @csrf
            <button class="btn btn-primary">Mark Sent</button>
        </form>
        <form method="POST" action="{{ route('proforma-invoices.destroy', $proformaInvoice) }}" style="display:inline;" onsubmit="return confirm('Delete this proforma?')">
            @csrf @method('DELETE')
            <button class="btn btn-danger">Delete</button>
        </form>
        @endif
        @if($proformaInvoice->status === 'sent')
        <form method="POST" action="{{ route('proforma-invoices.accept', $proformaInvoice) }}" style="display:inline;">
            @csrf
            <button class="btn btn-primary">Accept</button>
        </form>
        <form method="POST" action="{{ route('proforma-invoices.reject', $proformaInvoice) }}" style="display:inline;">
            @csrf
            <button class="btn btn-danger">Reject</button>
        </form>
        @endif
        @if($proformaInvoice->status === 'accepted')
        <form method="POST" action="{{ route('proforma-invoices.convert', $proformaInvoice) }}" style="display:inline;">
            @csrf
            <button class="btn btn-primary">Convert to Invoice</button>
        </form>
        @endif
        <button onclick="window.print()" class="btn btn-secondary">Print</button>
        <a href="{{ route('proforma-invoices.index') }}" class="btn btn-secondary">Back</a>
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('info'))<div class="alert alert-info">{{ session('info') }}</div>@endif

<div style="max-width:900px;">
<div class="card">
<div class="card-body">
<div class="pf-show-header-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
<div>
    <strong style="font-size:1.1rem;">{{ $business->name }}</strong><br>
    <span class="text-muted" style="font-size:0.9rem;text-transform:uppercase;letter-spacing:1px;">Proforma Invoice</span>
</div>
<div style="text-align:right;">
    <div style="font-size:1.5rem;font-weight:700;font-family:monospace;">{{ $proformaInvoice->proforma_number }}</div>
    <div class="text-muted">Issued: {{ $proformaInvoice->issue_date?->format('d M Y') }}</div>
    @if($proformaInvoice->valid_until)
    <div class="text-muted">Valid Until: {{ $proformaInvoice->valid_until?->format('d M Y') }}</div>
    @endif
    @php $badges=['draft'=>'badge-secondary','sent'=>'badge-blue','accepted'=>'badge-success','rejected'=>'badge-danger','converted'=>'badge-purple']; @endphp
    <span class="badge {{ $badges[$proformaInvoice->status] ?? 'badge-secondary' }}" style="display:inline-block;margin-top:6px;">{{ ucfirst($proformaInvoice->status) }}</span>
</div>
</div>

@if($proformaInvoice->customer)
<div style="margin-bottom:24px;padding:12px;background:var(--color-surface-2);border-radius:4px;">
<strong>Bill To:</strong><br>
{{ $proformaInvoice->customer->name }}<br>
@if($proformaInvoice->customer->phone){{ $proformaInvoice->customer->phone }}<br>@endif
@if($proformaInvoice->customer->email){{ $proformaInvoice->customer->email }}@endif
</div>
@endif

<table class="pf-show-table" style="width:100%;border-collapse:collapse;margin-bottom:24px;">
<thead><tr style="border-bottom:2px solid var(--color-text);">
    <th style="text-align:left;">Description</th>
    <th style="text-align:right;">Qty</th>
    <th style="text-align:right;">Unit Price</th>
    <th style="text-align:right;">Tax</th>
    <th style="text-align:right;">Total</th>
</tr></thead>
<tbody>
@foreach($proformaInvoice->items as $item)
<tr style="border-bottom:1px solid var(--color-border);">
    <td data-label="Description">{{ $item->description }}</td>
    <td data-label="Qty" style="text-align:right;">{{ $item->quantity }}</td>
    <td data-label="Unit Price" style="text-align:right;">{{ number_format($item->unit_price, 2) }}</td>
    <td data-label="Tax" style="text-align:right;">{{ $item->tax_rate }}%</td>
    <td data-label="Total" style="text-align:right;">{{ number_format($item->total, 2) }}</td>
</tr>
@endforeach
</tbody>
<tfoot>
<tr><td colspan="4" class="text-muted" style="text-align:right;">Subtotal</td><td style="text-align:right;">KSh {{ number_format($proformaInvoice->subtotal, 2) }}</td></tr>
<tr><td colspan="4" class="text-muted" style="text-align:right;">Tax</td><td style="text-align:right;">KSh {{ number_format($proformaInvoice->tax_amount, 2) }}</td></tr>
<tr style="font-weight:700;font-size:1.1rem;border-top:2px solid var(--color-text);">
    <td colspan="4" style="text-align:right;">Total</td>
    <td style="text-align:right;">KSh {{ number_format($proformaInvoice->total_amount, 2) }}</td>
</tr>
</tfoot>
</table>

@if($proformaInvoice->notes)
<div style="padding:12px;background:var(--color-surface-2);border-radius:4px;font-size:0.9rem;">
<strong>Notes:</strong> {{ $proformaInvoice->notes }}
</div>
@endif
</div>
</div>
</div>

<div style="max-width:900px;">
<div class="card">
<div class="card-body">
    @include('partials.attachments', ['modelType' => 'ProformaInvoice', 'modelId' => $proformaInvoice->id])
</div>
</div>
</div>
@endsection
