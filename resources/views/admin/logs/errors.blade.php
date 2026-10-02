@extends('admin.layouts.app')
@section('title', 'Errors — Developer Logs')
@section('subtitle', 'One line per bug')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Errors</h1>
        <p class="admin-page-subtitle">The same bug hitting many stores is one line. Resolved bugs come back if they happen again.</p>
    </div>
</div>
@include('admin.logs._tabs')

@if(session('success'))<div class="admin-alert admin-alert-success"><i class="ph-bold ph-check-circle"></i> {{ session('success') }}</div>@endif

<div class="admin-panel">
    <form method="GET" class="lg-filter">
        <div><label>Show</label>
            <select name="status">
                <option value="open" {{ $status === 'open' ? 'selected' : '' }}>Unresolved</option>
                <option value="resolved" {{ $status === 'resolved' ? 'selected' : '' }}>Resolved</option>
                <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All</option>
            </select></div>
        <div><label>Store</label>
            <select name="business_id"><option value="">All stores</option>
                @foreach($stores as $id => $name)<option value="{{ $id }}" {{ request('business_id') == $id ? 'selected' : '' }}>{{ $name }}</option>@endforeach
            </select></div>
        <div><label>Search</label><input type="text" name="q" value="{{ request('q') }}" placeholder="message, class or page"></div>
        <div><label>From</label><input type="date" name="from" value="{{ request('from') }}"></div>
        <div><label>To</label><input type="date" name="to" value="{{ request('to') }}"></div>
        <button class="btn btn-primary btn-sm" type="submit">Filter</button>
    </form>
    <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>Error</th><th>Where</th><th>Times</th><th>Stores</th><th>First seen</th><th>Last seen</th></tr></thead>
        <tbody>
        @forelse($groups as $g)
            @php $s = $samples[$g->last_id] ?? null; @endphp
            <tr>
                <td style="max-width:420px;">
                    <a href="{{ route('admin.logs.errors.show', $g->fingerprint) }}" style="color:var(--color-primary);text-decoration:none;font-weight:600;">{{ $s ? class_basename($s->exception) : 'Error' }}</a>
                    @if($g->open_rows == 0)<span class="admin-badge admin-badge-green">resolved</span>@endif
                    <div class="lg-muted" style="margin-top:2px;word-break:break-word;">{{ \Illuminate\Support\Str::limit($s->message ?? '', 140) }}</div>
                </td>
                <td class="lg-mono">{{ $s->route_name ?? $s->path ?? '—' }}<div class="lg-muted">{{ $s->file ?? '' }}:{{ $s->line ?? '' }}</div></td>
                <td class="lg-bad">{{ number_format($g->total) }}</td>
                <td>{{ $g->stores }}</td>
                <td class="lg-muted" style="white-space:nowrap;">{{ \Illuminate\Support\Carbon::parse($g->first_seen)->format('d M H:i') }}</td>
                <td class="lg-muted" style="white-space:nowrap;">{{ \Illuminate\Support\Carbon::parse($g->last_seen)->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="lg-muted" style="padding:22px;text-align:center;">No errors match. That is good news.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div style="padding:12px 16px;">{{ $groups->links() }}</div>
</div>
@endsection
