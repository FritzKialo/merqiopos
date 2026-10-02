@extends('layouts.app')
@section('title', 'Coupons')
@push('styles')
<style>
@media (min-width: 769px) {
    .cp-table th, .cp-table td { padding: 12px 16px; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h1 class="page-title">Coupons & Promo Codes</h1>
    <a href="{{ route('coupons.create') }}" class="btn btn-primary">+ New Coupon</a>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="card">
<div class="card-body" style="padding:0;">
<table class="cp-table" style="width:100%;border-collapse:collapse;">
<thead><tr style="border-bottom:1px solid var(--color-border);background:var(--color-surface-2);">
    <th style="text-align:left;">Code</th>
    <th style="text-align:left;">Name</th>
    <th style="text-align:center;width:90px;">Type</th>
    <th style="text-align:right;width:100px;">Value</th>
    <th style="text-align:center;width:100px;">Used / Max</th>
    <th style="text-align:center;width:70px;">Active</th>
    <th style="text-align:center;width:100px;">Expires</th>
    <th style="text-align:center;width:120px;">Actions</th>
</tr></thead>
<tbody>
@forelse($coupons as $coupon)
<tr style="border-bottom:1px solid var(--color-border);">
    <td data-label="Code">
        <span style="font-family:monospace;background:var(--color-surface-2);padding:2px 8px;border-radius:4px;font-size:0.9rem;">{{ $coupon->code }}</span>
    </td>
    <td data-label="Name">{{ $coupon->name }}</td>
    <td data-label="Type" style="font-size:0.85rem;">
        @if($coupon->discount_type === 'percentage')<span class="badge badge-purple">%</span>
        @else<span class="badge badge-blue">Fixed</span>@endif
    </td>
    <td data-label="Value" style="text-align:right;font-weight:600;">
        @if($coupon->discount_type === 'percentage'){{ $coupon->discount_value }}%
        @else KSh {{ number_format($coupon->discount_value, 2) }}@endif
    </td>
    <td data-label="Used / Max">{{ $coupon->usages_count }} / {{ $coupon->max_uses ?? '∞' }}</td>
    <td data-label="Active">
        @if($coupon->is_active)<span class="text-success">✓</span>@else<span class="text-danger">✗</span>@endif
    </td>
    <td data-label="Expires" class="text-muted" style="font-size:0.8rem;">{{ $coupon->expires_at ? $coupon->expires_at->format('d M Y') : '—' }}</td>
    <td data-label="Actions">
        <a href="{{ route('coupons.edit', $coupon) }}" class="btn btn-sm btn-secondary">Edit</a>
        <form method="POST" action="{{ route('coupons.destroy', $coupon) }}" style="display:inline;" onsubmit="return confirm('Delete?')">
            @csrf @method('DELETE') <button type="submit" class="btn btn-sm btn-danger">Del</button>
        </form>
    </td>
</tr>
@empty
<tr><td colspan="8" class="text-muted" style="padding:32px;text-align:center;">No coupons yet. <a href="{{ route('coupons.create') }}">Create one</a></td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
<div style="margin-top:16px;">{{ $coupons->links() }}</div>
@endsection
