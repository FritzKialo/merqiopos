@extends('layouts.app')
@section('title', 'Campaigns')
@push('styles')
<style>
@media (min-width: 769px) {
    .campaigns-table th, .campaigns-table td { padding: 12px 16px; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h1>Bulk Campaigns</h1>
    <a href="{{ route('campaigns.create') }}" class="btn btn-primary">+ New Campaign</a>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
<div class="card">
<div class="card-body" style="padding:0;">
<table class="campaigns-table" style="width:100%;border-collapse:collapse;">
<thead><tr style="border-bottom:1px solid var(--color-border);background:var(--color-surface-2);">
    <th style="text-align:left;">Name</th>
    <th style="text-align:center;">Channel</th>
    <th style="text-align:center;">Recipients</th>
    <th style="text-align:center;">Sent / Failed</th>
    <th style="text-align:center;">Status</th>
    <th style="text-align:center;">Date</th>
    <th style="text-align:center;">Actions</th>
</tr></thead>
<tbody>
@forelse($campaigns as $campaign)
<tr style="border-bottom:1px solid var(--color-border);">
    <td data-label="Name"><strong>{{ $campaign->name }}</strong></td>
    <td data-label="Channel" style="text-align:center;">
        @if($campaign->channel === 'sms')<span class="badge badge-blue">SMS</span>
        @elseif($campaign->channel === 'email')<span class="badge badge-purple">Email</span>
        @else<span class="badge badge-success">WhatsApp</span>@endif
    </td>
    <td data-label="Recipients" style="text-align:center;">{{ $campaign->recipient_count }}</td>
    <td data-label="Sent / Failed" style="text-align:center;">
        <span class="text-success">{{ $campaign->sent_count }}</span> /
        <span class="text-danger">{{ $campaign->failed_count }}</span>
    </td>
    <td data-label="Status" style="text-align:center;">
        @if($campaign->status === 'draft')<span class="badge badge-secondary">Draft</span>
        @elseif($campaign->status === 'sending')<span class="badge badge-blue">Sending</span>
        @elseif($campaign->status === 'sent')<span class="badge badge-success">Sent</span>
        @else<span class="badge badge-danger">Failed</span>@endif
    </td>
    <td data-label="Date" class="text-muted" style="text-align:center;font-size:0.85rem;">{{ $campaign->created_at->format('d M Y') }}</td>
    <td data-label="Actions" style="text-align:center;">
        <a href="{{ route('campaigns.show', $campaign) }}" class="btn btn-sm btn-secondary">View</a>
        @if($campaign->status === 'draft')
        <form method="POST" action="{{ route('campaigns.send', $campaign) }}" style="display:inline;" onsubmit="return confirm('Send this campaign to {{ $campaign->recipient_count }} recipients?')">
            @csrf
            <button type="submit" class="btn btn-sm btn-primary">Send</button>
        </form>
        @endif
        <form method="POST" action="{{ route('campaigns.destroy', $campaign) }}" style="display:inline;" onsubmit="return confirm('Delete this campaign?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
        </form>
    </td>
</tr>
@empty
<tr><td colspan="7" class="text-muted" style="padding:32px;text-align:center;">No campaigns yet. <a href="{{ route('campaigns.create') }}">Create your first campaign</a></td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
<div style="margin-top:16px;">{{ $campaigns->links() }}</div>
@endsection
