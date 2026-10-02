@extends('admin.layouts.app')
@section('title', 'Activity — Developer Logs')
@section('subtitle', 'What each store did')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Activity</h1>
        <p class="admin-page-subtitle">Every form submission and sign-in, plus every page that failed. Page views are sampled. Only the page, the action and the result are kept — never what was typed.</p>
    </div>
</div>
@include('admin.logs._tabs')

<div class="admin-panel">
    <form method="GET" class="lg-filter">
        <div><label>Store</label>
            <select name="business_id"><option value="">All stores</option>
                @foreach($stores as $id => $name)<option value="{{ $id }}" {{ request('business_id') == $id ? 'selected' : '' }}>{{ $name }}</option>@endforeach
            </select></div>
        <div><label>Person</label><input type="text" name="user" value="{{ request('user') }}" placeholder="name or email"></div>
        <div><label>Result</label>
            <select name="outcome">
                <option value="">Everything</option>
                <option value="problems" {{ request('outcome') === 'problems' ? 'selected' : '' }}>All problems</option>
                @foreach(['ok' => 'Succeeded', 'validation' => 'Rejected input', 'failed' => 'Failed', 'denied' => 'Not allowed', 'error' => 'Crashed'] as $k => $v)
                    <option value="{{ $k }}" {{ request('outcome') === $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select></div>
        <div><label>Type</label>
            <select name="kind"><option value="">All</option>
                @foreach(['request' => 'Actions', 'login' => 'Sign-ins', 'login_failed' => 'Failed sign-ins', 'logout' => 'Sign-outs'] as $k => $v)
                    <option value="{{ $k }}" {{ request('kind') === $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select></div>
        <div><label>Page / action</label><input type="text" name="route" value="{{ request('route') }}" placeholder="e.g. sales.store"></div>
        <div><label>From</label><input type="date" name="from" value="{{ request('from') }}"></div>
        <div><label>To</label><input type="date" name="to" value="{{ request('to') }}"></div>
        <button class="btn btn-primary btn-sm" type="submit">Filter</button>
    </form>
    <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>When</th><th>Store</th><th>Person</th><th>Action</th><th>Result</th><th>Detail</th><th>Time</th></tr></thead>
        <tbody>
        @forelse($logs as $l)
            @php
                $badge = match($l->outcome) { 'ok' => 'admin-badge-green', 'error' => 'admin-badge-red', 'denied' => 'admin-badge-gray', default => 'admin-badge-indigo' };
                $label = match($l->outcome) { 'ok' => 'OK', 'validation' => 'Rejected input', 'failed' => 'Failed', 'denied' => 'Not allowed', 'error' => 'Crashed', default => $l->outcome };
            @endphp
            <tr>
                <td class="lg-muted" style="white-space:nowrap;">{{ $l->created_at?->format('d M H:i:s') }}</td>
                <td>{{ $stores[$l->business_id] ?? ($l->business_id ? 'Store #' . $l->business_id : '—') }}</td>
                <td>{{ $l->user?->name ?? '—' }}<div class="lg-muted">{{ $l->role }}</div></td>
                <td>
                    @if($l->kind !== 'request')<span class="admin-badge admin-badge-gray">{{ str_replace('_', ' ', $l->kind) }}</span>@endif
                    <div class="lg-mono">{{ $l->method }} {{ $l->route_name ?: $l->path }}</div>
                </td>
                <td><span class="admin-badge {{ $badge }}">{{ $label }}</span> <span class="lg-muted">{{ $l->status }}</span></td>
                <td class="lg-muted" style="max-width:280px;word-break:break-word;">{{ $l->message }}</td>
                <td class="lg-muted">{{ $l->duration_ms !== null ? number_format($l->duration_ms) . ' ms' : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="lg-muted" style="padding:22px;text-align:center;">Nothing recorded for these filters.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div style="padding:12px 16px;">{{ $logs->links() }}</div>
</div>
@endsection
