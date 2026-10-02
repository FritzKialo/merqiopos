@extends('layouts.app')
@section('title', 'Activity Log')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Activity Log</h1>
        <p class="page-subtitle">What your team did, and what did not work: rejected entries, refused actions and sign-ins. Kept for about {{ config('security.log_retention_days', 60) }} days. What people type is never recorded.</p>
    </div>
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
            @if($stores->count() > 1)
            <div><label class="form-label" style="font-size:.8rem;">Store</label>
                <select name="business_id" class="form-control"><option value="">All stores</option>
                    @foreach($stores as $id => $name)<option value="{{ $id }}" {{ request('business_id') == $id ? 'selected' : '' }}>{{ $name }}</option>@endforeach
                </select></div>
            @endif
            <div><label class="form-label" style="font-size:.8rem;">Person</label><input type="text" name="user" class="form-control" value="{{ request('user') }}" placeholder="name"></div>
            <div><label class="form-label" style="font-size:.8rem;">Result</label>
                <select name="outcome" class="form-control">
                    <option value="">Everything</option>
                    <option value="problems" {{ request('outcome') === 'problems' ? 'selected' : '' }}>Only problems</option>
                    <option value="ok" {{ request('outcome') === 'ok' ? 'selected' : '' }}>Succeeded</option>
                    <option value="validation" {{ request('outcome') === 'validation' ? 'selected' : '' }}>Entry rejected</option>
                    <option value="denied" {{ request('outcome') === 'denied' ? 'selected' : '' }}>Not allowed</option>
                    <option value="failed" {{ request('outcome') === 'failed' ? 'selected' : '' }}>Did not work</option>
                </select></div>
            <div><label class="form-label" style="font-size:.8rem;">From</label><input type="date" name="from" class="form-control" value="{{ request('from') }}"></div>
            <div><label class="form-label" style="font-size:.8rem;">To</label><input type="date" name="to" class="form-control" value="{{ request('to') }}"></div>
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <div class="table-wrap">
        <table class="table">
            <thead><tr><th>When</th>@if($stores->count() > 1)<th>Store</th>@endif<th>Person</th><th>What</th><th>Result</th><th>Detail</th></tr></thead>
            <tbody>
            @forelse($logs as $l)
                @php
                    $label = match($l->outcome) { 'ok' => 'OK', 'validation' => 'Entry rejected', 'failed' => 'Did not work', 'denied' => 'Not allowed', 'error' => 'Error', default => $l->outcome };
                    $color = match($l->outcome) { 'ok' => '#166534', 'error' => '#b91c1c', 'denied' => '#6b7280', default => '#b45309' };
                    $what = $l->kind === 'login' ? 'Signed in' : ($l->kind === 'login_failed' ? 'Failed sign-in' : ucwords(str_replace(['.', '-', '_'], ' ', $l->route_name ?: $l->path)));
                @endphp
                <tr>
                    <td data-label="When" style="white-space:nowrap;">{{ $l->created_at?->format('d M H:i') }}</td>
                    @if($stores->count() > 1)<td data-label="Store">{{ $stores[$l->business_id] ?? '—' }}</td>@endif
                    <td data-label="Person">{{ $l->user?->name ?? '—' }}</td>
                    <td data-label="What">{{ $what }}</td>
                    <td data-label="Result"><span style="font-weight:600;color:{{ $color }};">{{ $label }}</span></td>
                    <td data-label="Detail" style="font-size:.85rem;color:var(--color-text-muted);max-width:320px;">{{ $l->message }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--color-text-muted);">Nothing recorded for these filters yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
<div style="margin-top:12px;">{{ $logs->links() }}</div>
@endsection
