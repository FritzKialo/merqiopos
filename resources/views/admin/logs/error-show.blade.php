@extends('admin.layouts.app')
@section('title', 'Error detail — Developer Logs')
@section('subtitle', class_basename($sample->exception))

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">{{ class_basename($sample->exception) }}</h1>
        <p class="admin-page-subtitle">{{ $sample->exception }}</p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('admin.logs.errors') }}" class="btn btn-secondary btn-sm">← All errors</a>
        @if($open)
        <form method="POST" action="{{ route('admin.logs.errors.resolve', $fingerprint) }}">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">Mark resolved</button>
        </form>
        @endif
    </div>
</div>
@include('admin.logs._tabs')

<div class="admin-panel" style="margin-bottom:20px;">
    <div style="padding:18px;">
        <div class="lg-muted" style="margin-bottom:4px;">Message</div>
        <div style="font-weight:600;word-break:break-word;">{{ $sample->message }}</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-top:16px;">
            <div><div class="lg-muted">Happened</div><div class="lg-bad">{{ number_format($rows->sum('count')) }} times</div></div>
            <div><div class="lg-muted">First seen</div><div>{{ $rows->min('first_seen_at')?->format('d M Y H:i') }}</div></div>
            <div><div class="lg-muted">Last seen</div><div>{{ $sample->last_seen_at?->format('d M Y H:i') }} ({{ $sample->last_seen_at?->diffForHumans() }})</div></div>
            <div><div class="lg-muted">Where</div><div class="lg-mono">{{ $sample->method }} {{ $sample->path }}</div></div>
            <div><div class="lg-muted">Route</div><div class="lg-mono">{{ $sample->route_name ?? '—' }}</div></div>
            <div><div class="lg-muted">Code</div><div class="lg-mono">{{ $sample->file }}:{{ $sample->line }}</div></div>
            <div><div class="lg-muted">Reference</div><div class="lg-mono">{{ $sample->request_id }}</div></div>
        </div>
    </div>
</div>

<div class="admin-panel" style="margin-bottom:20px;">
    <div class="admin-panel-header"><h3 class="admin-panel-title">Stack trace (first frames, no arguments)</h3></div>
    <div style="padding:16px;"><pre class="lg-pre">{{ $sample->trace ?: 'No trace recorded.' }}</pre></div>
</div>

<div class="admin-panel">
    <div class="admin-panel-header"><h3 class="admin-panel-title">Which stores</h3></div>
    <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>Store</th><th>Times</th><th>Users hit</th><th>Last seen</th></tr></thead>
        <tbody>
        @foreach($byStore as $bid => $row)
            <tr>
                <td>@if($bid)<a href="{{ route('admin.logs.activity', ['business_id' => $bid]) }}" style="color:var(--color-primary);text-decoration:none;font-weight:600;">{{ $storeNames[$bid] ?? 'Store #' . $bid }}</a>@else <span class="lg-muted">Not tied to a store (background job or public page)</span>@endif</td>
                <td class="lg-bad">{{ number_format($row['count']) }}</td>
                <td>{{ $row['users'] }}</td>
                <td class="lg-muted">{{ $row['last']?->diffForHumans() }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div>
@endsection
