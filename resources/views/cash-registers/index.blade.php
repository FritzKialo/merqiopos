@extends('layouts.app')
@section('title', 'Cash Registers')
@push('styles')
<style>
@media (min-width: 769px) {
    .cr-index-table th, .cr-index-table td { padding: 12px 16px; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h1>Cash Register Sessions</h1>
    @if(!$openRegister)
    <a href="{{ route('cash-registers.open-form') }}" class="btn btn-primary">+ Open Register</a>
    @endif
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

@if($openRegister)
<div class="alert alert-success" style="border-radius:8px;padding:20px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;">
    <div>
        <strong style="font-size:1.1rem;">Register Open</strong>
        <div style="margin-top:4px;">
            Opened at {{ $openRegister->opened_at->format('d M Y H:i') }} by {{ $openRegister->user->name }}
            &nbsp;|&nbsp; Float: KSh {{ number_format($openRegister->opening_float, 2) }}
            &nbsp;|&nbsp; {{ $openRegister->entries->count() }} entries
        </div>
    </div>
    <a href="{{ route('cash-registers.show', $openRegister) }}" class="btn btn-primary">Continue Session</a>
</div>
@endif

<div class="card"><div class="card-body" style="padding:0;">
<table class="cr-index-table" style="width:100%;border-collapse:collapse;">
<thead><tr style="border-bottom:1px solid var(--color-border);">
    <th style="text-align:left;">Date</th>
    <th style="text-align:left;">Opened By</th>
    <th style="text-align:right;">Opening Float</th>
    <th style="text-align:right;">Expected</th>
    <th style="text-align:right;">Actual</th>
    <th style="text-align:right;">Difference</th>
    <th style="text-align:left;">Status</th>
    <th style="text-align:right;">Actions</th>
</tr></thead>
<tbody>
@forelse($registers as $r)
<tr style="border-bottom:1px solid var(--color-border);">
    <td data-label="Date">{{ $r->opened_at->format('d M Y H:i') }}</td>
    <td data-label="Opened By">{{ $r->user->name }}</td>
    <td data-label="Opening Float" style="text-align:right;">{{ number_format($r->opening_float, 2) }}</td>
    <td data-label="Expected" style="text-align:right;">{{ $r->expected_closing ? number_format($r->expected_closing, 2) : '—' }}</td>
    <td data-label="Actual" style="text-align:right;">{{ $r->actual_closing !== null ? number_format($r->actual_closing, 2) : '—' }}</td>
    <td data-label="Difference" style="text-align:right;">
        @if($r->difference !== null)
            <span class="{{ $r->difference >= 0 ? 'text-success' : 'text-danger' }}" style="font-weight:600;">
                {{ $r->difference >= 0 ? '+' : '' }}{{ number_format($r->difference, 2) }}
            </span>
        @else
            —
        @endif
    </td>
    <td data-label="Status">
        @if($r->status === 'open')<span class="badge badge-success">Open</span>
        @else<span class="badge badge-secondary">{{ ucfirst($r->status) }}</span>@endif
    </td>
    <td data-label="Actions" style="text-align:right;">
        <a href="{{ route('cash-registers.show', $r) }}" class="btn btn-secondary" style="padding:4px 10px;font-size:0.8rem;">View</a>
    </td>
</tr>
@empty
<tr><td colspan="8" class="text-muted" style="padding:40px;text-align:center;">No register sessions yet.</td></tr>
@endforelse
</tbody>
</table>
</div></div>
{{ $registers->links() }}
@endsection
