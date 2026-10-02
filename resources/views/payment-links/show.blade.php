@extends('layouts.app')
@section('title', 'Payment Link: ' . $link->title)
@push('styles')
<style>
@media (max-width: 900px) {
    .pl-show-layout { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h1>{{ $link->title }}</h1>
    <a href="{{ route('payment-links.index') }}" class="btn btn-secondary">← Back</a>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="pl-show-layout" style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
<div>
<div class="card" style="margin-bottom:16px;">
<div class="card-body">
    <h3 style="margin:0 0 16px;">Link Details</h3>
    <table class="table-plain" style="width:100%;border-collapse:collapse;">
        <tr><td class="text-muted" style="padding:8px 0;width:140px;">Amount</td><td style="padding:8px 0;font-size:1.3rem;font-weight:700;">KSh {{ number_format($link->amount, 2) }}</td></tr>
        <tr><td class="text-muted" style="padding:8px 0;">Customer</td><td style="padding:8px 0;">{{ $link->customer->name ?? '—' }}</td></tr>
        @if($link->description)<tr><td class="text-muted" style="padding:8px 0;">Description</td><td style="padding:8px 0;">{{ $link->description }}</td></tr>@endif
        <tr><td class="text-muted" style="padding:8px 0;">Expires</td><td style="padding:8px 0;">{{ $link->expires_at ? $link->expires_at->format('d M Y H:i') : 'Never' }}</td></tr>
        @if($link->paid_at)<tr><td class="text-muted" style="padding:8px 0;">Paid At</td><td class="text-success" style="padding:8px 0;">{{ $link->paid_at->format('d M Y H:i') }}</td></tr>@endif
        <tr><td class="text-muted" style="padding:8px 0;">Status</td><td style="padding:8px 0;">
            @if($link->status === 'active')<span class="badge badge-success">Active</span>
            @elseif($link->status === 'paid')<span class="badge badge-blue">Paid</span>
            @elseif($link->status === 'expired')<span class="badge badge-danger">Expired</span>
            @else<span class="badge badge-secondary">Cancelled</span>@endif
        </td></tr>
    </table>
</div>
</div>

<div class="card">
<div class="card-body">
    <h3 style="margin:0 0 12px;">Payment URL</h3>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <input type="text" id="pay-url" value="{{ $link->public_url }}" class="form-control" readonly style="font-family:monospace;font-size:0.85rem;min-width:180px;flex:1;">
        <button onclick="copyLink()" id="copy-btn" class="btn btn-secondary" style="white-space:nowrap;">Copy Link</button>
    </div>
    <div style="margin-top:8px;">
        <a href="{{ $link->public_url }}" target="_blank" style="font-size:0.85rem;color:var(--color-primary);">Open in new tab →</a>
    </div>
</div>
</div>
</div>

<div>
<div class="card">
<div class="card-body">
    <h3 style="margin:0 0 16px;">Actions</h3>
    @if($link->customer && $link->customer->phone && $link->status === 'active')
    <form method="POST" action="{{ route('payment-links.send-sms', $link) }}" style="margin-bottom:12px;">
        @csrf
        <button type="submit" class="btn btn-primary" style="width:100%;">Send SMS to Customer</button>
    </form>
    <p class="text-muted" style="font-size:0.8rem;margin-bottom:16px;">Will send to: {{ $link->customer->phone }}</p>
    @endif

    @if($link->status === 'active')
    <form method="POST" action="{{ route('payment-links.cancel', $link) }}" onsubmit="return confirm('Cancel this payment link?')">
        @csrf
        <button type="submit" class="btn btn-secondary" style="width:100%;margin-bottom:12px;">Cancel Link</button>
    </form>
    @endif

    <form method="POST" action="{{ route('payment-links.destroy', $link) }}" onsubmit="return confirm('Delete this payment link?')">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-danger" style="width:100%;">Delete</button>
    </form>
</div>
</div>
</div>
</div>

<script>
function copyLink() {
    const url = document.getElementById('pay-url').value;
    navigator.clipboard.writeText(url).then(() => {
        const btn = document.getElementById('copy-btn');
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = 'Copy Link', 2000);
    });
}
</script>
@endsection
