@extends('layouts.app')
@section('title', 'Payment Links')
@push('styles')
<style>
@media (min-width: 769px) {
    .pl-table th, .pl-table td { padding: 12px 16px; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h1>Payment Links</h1>
    <a href="{{ route('payment-links.create') }}" class="btn btn-primary">+ New Link</a>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="card">
<div class="card-body" style="padding:0;">
<table class="pl-table" style="width:100%;border-collapse:collapse;">
<thead><tr style="border-bottom:1px solid var(--color-border);background:var(--color-surface-2);">
    <th style="text-align:left;">Title</th>
    <th style="text-align:left;">Customer</th>
    <th style="text-align:right;width:120px;">Amount</th>
    <th style="text-align:left;">Link</th>
    <th style="text-align:center;width:80px;">Status</th>
    <th style="text-align:center;width:90px;">Expires</th>
    <th style="text-align:center;width:120px;">Actions</th>
</tr></thead>
<tbody>
@forelse($links as $link)
<tr style="border-bottom:1px solid var(--color-border);">
    <td data-label="Title" style="font-weight:500;">{{ $link->title }}</td>
    <td data-label="Customer" class="text-muted">{{ $link->customer->name ?? '—' }}</td>
    <td data-label="Amount" style="text-align:right;font-weight:600;">KSh {{ number_format($link->amount, 2) }}</td>
    <td data-label="Link">
        <span id="url-{{ $link->id }}" class="text-muted" style="font-size:0.75rem;display:inline-block;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $link->public_url }}</span>
        <button onclick="copyUrl('{{ $link->public_url }}','btn-{{ $link->id }}')" id="btn-{{ $link->id }}" class="btn btn--outline btn--sm" style="padding:2px 8px;margin-left:4px;">Copy</button>
    </td>
    <td data-label="Status" style="text-align:center;">
        @if($link->status === 'active')<span class="badge badge-success">Active</span>
        @elseif($link->status === 'paid')<span class="badge badge-blue">Paid</span>
        @elseif($link->status === 'expired')<span class="badge badge-danger">Expired</span>
        @else<span class="badge badge-secondary">Cancelled</span>@endif
    </td>
    <td data-label="Expires" class="text-muted" style="text-align:center;font-size:0.8rem;">{{ $link->expires_at ? $link->expires_at->format('d M Y') : '—' }}</td>
    <td data-label="Actions" style="text-align:center;">
        <a href="{{ route('payment-links.show', $link) }}" class="btn btn-sm btn-secondary">View</a>
        <form method="POST" action="{{ route('payment-links.destroy', $link) }}" style="display:inline;" onsubmit="return confirm('Delete?')">
            @csrf @method('DELETE') <button type="submit" class="btn btn-sm btn-danger">Del</button>
        </form>
    </td>
</tr>
@empty
<tr><td colspan="7" class="text-muted" style="padding:32px;text-align:center;">No payment links yet.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
<div style="margin-top:16px;">{{ $links->links() }}</div>
<script>
function copyUrl(url, btnId) {
    navigator.clipboard.writeText(url).then(() => {
        const btn = document.getElementById(btnId);
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = 'Copy', 2000);
    });
}
</script>
@endsection
