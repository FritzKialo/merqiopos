@extends('layouts.app')
@section('title', $campaign->name)
@push('styles')
<style>
@media (max-width: 900px) {
    .campaign-stats-grid { grid-template-columns: 1fr 1fr !important; }
}
@media (max-width: 500px) {
    .campaign-stats-grid { grid-template-columns: 1fr !important; }
}
@media (min-width: 769px) {
    .campaign-recipients-table th, .campaign-recipients-table td { padding: 10px 16px; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h1>{{ $campaign->name }}</h1>
    <a href="{{ route('campaigns.index') }}" class="btn btn-secondary">← Back</a>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="campaign-stats-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;">
    <div class="card" style="text-align:center;padding:20px;">
        <div style="font-size:2rem;font-weight:700;">{{ $campaign->recipient_count }}</div>
        <div class="text-muted" style="font-size:0.85rem;">Recipients</div>
    </div>
    <div class="card" style="text-align:center;padding:20px;">
        <div class="text-success" style="font-size:2rem;font-weight:700;">{{ $campaign->sent_count }}</div>
        <div class="text-muted" style="font-size:0.85rem;">Delivered</div>
    </div>
    <div class="card" style="text-align:center;padding:20px;">
        <div class="text-danger" style="font-size:2rem;font-weight:700;">{{ $campaign->failed_count }}</div>
        <div class="text-muted" style="font-size:0.85rem;">Failed</div>
    </div>
    <div class="card" style="text-align:center;padding:20px;">
        <div style="font-size:1.2rem;font-weight:700;text-transform:capitalize;">
            @if($campaign->status === 'draft')<span class="text-muted">Draft</span>
            @elseif($campaign->status === 'sending')<span class="text-info">Sending…</span>
            @elseif($campaign->status === 'sent')<span class="text-success">Sent</span>
            @else<span class="text-danger">Failed</span>@endif
        </div>
        <div class="text-muted" style="font-size:0.85rem;">Status</div>
    </div>
</div>

<div class="card" style="margin-bottom:16px;">
<div class="card-body">
    <h3 style="margin:0 0 12px;">Campaign Details</h3>
    <table class="table-plain" style="width:100%;border-collapse:collapse;">
        <tr><td class="text-muted" style="padding:8px 0;width:160px;">Channel</td><td style="padding:8px 0;text-transform:capitalize;">{{ $campaign->channel }}</td></tr>
        <tr><td class="text-muted" style="padding:8px 0;">Segment</td><td style="padding:8px 0;text-transform:capitalize;">{{ str_replace('_',' ', $campaign->segment_type) }}</td></tr>
        <tr><td class="text-muted" style="padding:8px 0;">Created</td><td style="padding:8px 0;">{{ $campaign->created_at->format('d M Y H:i') }}</td></tr>
        @if($campaign->sent_at)<tr><td class="text-muted" style="padding:8px 0;">Sent At</td><td style="padding:8px 0;">{{ $campaign->sent_at->format('d M Y H:i') }}</td></tr>@endif
    </table>
    <div style="margin-top:12px;padding:12px;background:var(--color-surface-2);border-radius:4px;">
        <strong>Message Template:</strong>
        <p style="margin:8px 0 0;white-space:pre-wrap;">{{ $campaign->message_template }}</p>
    </div>
    @if($campaign->status === 'draft')
    <div style="margin-top:16px;">
        <form method="POST" action="{{ route('campaigns.send', $campaign) }}" onsubmit="return confirm('Send to {{ $campaign->recipient_count }} recipients now?')">
            @csrf
            <button type="submit" class="btn btn-primary">Send Campaign Now</button>
        </form>
    </div>
    @endif
</div>
</div>

<div class="card">
<div class="card-body" style="padding:0;">
    <div style="padding:16px;border-bottom:1px solid var(--color-border);"><h3 style="margin:0;">Recipients ({{ $campaign->recipients->count() }})</h3></div>
    <table class="campaign-recipients-table" style="width:100%;border-collapse:collapse;">
    <thead><tr style="background:var(--color-surface-2);">
        <th style="text-align:left;">Customer</th>
        <th style="text-align:left;">Phone / Email</th>
        <th style="text-align:center;">Status</th>
        <th style="text-align:left;">Error</th>
    </tr></thead>
    <tbody>
    @foreach($campaign->recipients as $recipient)
    <tr style="border-bottom:1px solid var(--color-border);">
        <td data-label="Customer">{{ $recipient->customer->name ?? '—' }}</td>
        <td data-label="Phone / Email" class="text-muted" style="font-size:0.85rem;">{{ $recipient->phone ?: $recipient->email ?: '—' }}</td>
        <td data-label="Status" style="text-align:center;">
            @if($recipient->status === 'sent')<span class="text-success" style="font-size:0.85rem;">✓ Sent</span>
            @elseif($recipient->status === 'failed')<span class="text-danger" style="font-size:0.85rem;">✗ Failed</span>
            @else<span class="text-muted" style="font-size:0.85rem;">Pending</span>@endif
        </td>
        <td data-label="Error" class="text-danger" style="font-size:0.8rem;">{{ $recipient->error_message ?? '' }}</td>
    </tr>
    @endforeach
    </tbody>
    </table>
</div>
</div>
@endsection
