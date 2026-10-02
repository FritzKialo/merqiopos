@extends('layouts.app')
@section('title', 'Product Waitlist')
@push('styles')
<style>
@media (min-width: 769px) {
    .waitlist-table th, .waitlist-table td { padding: 12px 16px; }
}
</style>
@endpush
@section('content')
<div class="page-header"><h1>Product Waitlist</h1></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

{{-- Filters --}}
<form method="GET" style="display:flex;gap:12px;margin-bottom:20px;align-items:flex-end;flex-wrap:wrap;">
    <div>
        <label class="form-label">Product</label>
        <select name="product_id" class="form-control">
            <option value="">All Products</option>
            @foreach($products as $p)
            <option value="{{ $p->id }}" @selected(request('product_id') == $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label">Status</label>
        <select name="status" class="form-control">
            <option value="">All Statuses</option>
            <option value="waiting" @selected(request('status') === 'waiting')>Waiting</option>
            <option value="notified" @selected(request('status') === 'notified')>Notified</option>
            <option value="fulfilled" @selected(request('status') === 'fulfilled')>Fulfilled</option>
            <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
        </select>
    </div>
    <button type="submit" class="btn btn-secondary">Filter</button>
    @if(request('product_id'))
    <form method="POST" action="{{ route('inventory.waitlist.notify-all') }}" style="margin:0;">
        @csrf
        <input type="hidden" name="product_id" value="{{ request('product_id') }}">
        <button type="submit" class="btn btn-primary" onclick="return confirm('Notify all waiting customers for this product?')">Notify All Waiting</button>
    </form>
    @endif
</form>

<div class="card"><div class="card-body" style="padding:0;">
<div class="table-wrapper">
<table class="waitlist-table" style="width:100%;border-collapse:collapse;">
<thead><tr style="border-bottom:1px solid var(--color-border);">
    <th style="text-align:left;">Customer</th>
    <th style="text-align:left;">Product</th>
    <th style="text-align:right;">Qty Wanted</th>
    <th style="text-align:left;">Phone</th>
    <th style="text-align:left;">Email</th>
    <th style="text-align:left;">Date</th>
    <th style="text-align:left;">Status</th>
    <th style="text-align:right;">Actions</th>
</tr></thead>
<tbody>
@forelse($entries as $entry)
@php $badgeClasses=['waiting'=>'badge-blue','notified'=>'badge-warning','fulfilled'=>'badge-success','cancelled'=>'badge-secondary']; @endphp
<tr style="border-bottom:1px solid var(--color-border);">
    <td data-label="Customer">{{ $entry->customer_name }}</td>
    <td data-label="Product">{{ $entry->product->name }}</td>
    <td data-label="Qty Wanted" style="text-align:right;">{{ $entry->quantity_wanted }}</td>
    <td data-label="Phone">{{ $entry->customer_phone ?? '—' }}</td>
    <td data-label="Email">{{ $entry->customer_email ?? '—' }}</td>
    <td data-label="Date" class="text-muted" style="font-size:0.85rem;">{{ $entry->created_at->format('d M Y') }}</td>
    <td data-label="Status">
        <span class="badge {{ $badgeClasses[$entry->status] ?? 'badge-secondary' }}">{{ ucfirst($entry->status) }}</span>
        @if($entry->notified_at)
        <div class="text-muted" style="font-size:0.75rem;">{{ $entry->notified_at->format('d M H:i') }}</div>
        @endif
    </td>
    <td data-label="Actions" style="text-align:right;white-space:nowrap;">
        @if($entry->status === 'waiting')
        <form method="POST" action="{{ route('inventory.waitlist.notify', $entry) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-secondary" style="padding:4px 10px;font-size:0.8rem;">Notify</button>
        </form>
        @endif
        <form method="POST" action="{{ route('inventory.waitlist.destroy', $entry) }}" style="display:inline;">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger" style="padding:4px 10px;font-size:0.8rem;" onclick="return confirm('Remove this entry?')">×</button>
        </form>
    </td>
</tr>
@empty
<tr><td colspan="8" class="text-muted" style="padding:40px;text-align:center;">No waitlist entries.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div></div>
{{ $entries->links() }}
@endsection
