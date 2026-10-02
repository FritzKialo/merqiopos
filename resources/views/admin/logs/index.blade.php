@extends('admin.layouts.app')
@section('title', 'Developer Logs')
@section('subtitle', 'What stores do, and what fails')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Developer Logs</h1>
        <p class="admin-page-subtitle">Health of the platform over the last 24 hours. Nothing a user typed is ever stored here.</p>
    </div>
</div>
@include('admin.logs._tabs')

<div class="admin-stats admin-stats-5">
    <div class="admin-stat-card {{ $stats['errors_24h'] ? 'admin-stat-red' : 'admin-stat-emerald' }}">
        <div class="admin-stat-label">Errors (24h)</div>
        <div class="admin-stat-value">{{ number_format($stats['errors_24h']) }}</div>
        <div class="admin-stat-sub">{{ $stats['error_groups'] }} different bug(s) · {{ $stats['open_groups'] }} unresolved overall</div>
    </div>
    <div class="admin-stat-card admin-stat-violet">
        <div class="admin-stat-label">Failed actions (24h)</div>
        <div class="admin-stat-value">{{ number_format($stats['failed_24h']) }}</div>
        <div class="admin-stat-sub">rejected forms, refused actions, crashes</div>
    </div>
    <div class="admin-stat-card admin-stat-indigo">
        <div class="admin-stat-label">Permission denials</div>
        <div class="admin-stat-value">{{ number_format($stats['denied_24h']) }}</div>
    </div>
    <div class="admin-stat-card {{ $stats['bad_logins_24h'] > 10 ? 'admin-stat-red' : 'admin-stat-emerald' }}">
        <div class="admin-stat-label">Failed sign-ins</div>
        <div class="admin-stat-value">{{ number_format($stats['bad_logins_24h']) }}</div>
    </div>
    <div class="admin-stat-card admin-stat-emerald">
        <div class="admin-stat-label">Active stores (24h)</div>
        <div class="admin-stat-value">{{ number_format($stats['active_stores']) }}</div>
        <div class="admin-stat-sub">{{ number_format($stats['actions_24h']) }} actions recorded</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;">
    <div class="admin-panel">
        <div class="admin-panel-header"><h3 class="admin-panel-title">Stores with the most trouble (7 days)</h3></div>
        <div class="admin-table-wrap"><table class="admin-table">
            <thead><tr><th>Store</th><th>Problems</th><th>Actions</th><th>Last seen</th></tr></thead>
            <tbody>
            @forelse($troubled as $t)
                <tr>
                    <td><a href="{{ route('admin.logs.activity', ['business_id' => $t->business_id, 'outcome' => 'problems']) }}" style="font-weight:600;color:var(--color-primary);text-decoration:none;">{{ $storeNames[$t->business_id] ?? 'Store #' . $t->business_id }}</a></td>
                    <td class="{{ $t->bad ? 'lg-bad' : '' }}">{{ $t->bad }}</td>
                    <td>{{ $t->total }}</td>
                    <td class="lg-muted">{{ \Illuminate\Support\Carbon::parse($t->last_seen)->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="lg-muted" style="padding:18px;">No activity recorded yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    <div class="admin-panel">
        <div class="admin-panel-header"><h3 class="admin-panel-title">Pages that fail most (7 days)</h3></div>
        <div class="admin-table-wrap"><table class="admin-table">
            <thead><tr><th>Page / action</th><th>Failures</th><th>Stores</th></tr></thead>
            <tbody>
            @forelse($failingRoutes as $r)
                <tr>
                    <td><a href="{{ route('admin.logs.activity', ['route' => $r->route_name, 'outcome' => 'problems']) }}" class="lg-mono" style="color:var(--color-primary);text-decoration:none;">{{ $r->route_name }}</a></td>
                    <td class="lg-bad">{{ $r->bad }}</td>
                    <td>{{ $r->stores }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="lg-muted" style="padding:18px;">Nothing has failed.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    <div class="admin-panel">
        <div class="admin-panel-header"><h3 class="admin-panel-title">Slowest actions (7 days, average)</h3></div>
        <div class="admin-table-wrap"><table class="admin-table">
            <thead><tr><th>Page / action</th><th>Avg</th><th>Worst</th><th>Hits</th></tr></thead>
            <tbody>
            @forelse($slowRoutes as $r)
                <tr>
                    <td class="lg-mono">{{ $r->route_name }}</td>
                    <td class="{{ $r->avg_ms > 1500 ? 'lg-bad' : '' }}">{{ number_format($r->avg_ms) }} ms</td>
                    <td class="lg-muted">{{ number_format($r->max_ms) }} ms</td>
                    <td>{{ $r->hits }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="lg-muted" style="padding:18px;">Not enough data yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</div>
@endsection
