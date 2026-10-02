@extends('layouts.app')
@section('title', 'Audit Log')
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Audit Log</h1>
            <p class="page-subtitle">Track all significant actions in your business</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('audit-log.index') }}" style="display:flex;gap:1rem;margin-bottom:1rem;align-items:flex-end;flex-wrap:wrap;">
        <div class="form-group" style="margin:0;">
            <label class="form-label">Event contains</label>
            <input type="text" name="event" class="form-control" value="{{ request('event') }}" placeholder="e.g. sale.created">
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label">From</label>
            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label">To</label>
            <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
        </div>
        <button type="submit" class="btn btn--outline">Filter</button>
        <a href="{{ route('audit-log.index') }}" class="btn btn--outline">Clear</a>
    </form>

    <div class="table-card">
        <table class="table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Event</th>
                    <th>Subject</th>
                    <th>Metadata</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td data-label="Timestamp" style="white-space:nowrap;">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                    <td data-label="User">{{ $log->user?->name ?? 'System' }}</td>
                    <td data-label="Event"><code style="background:var(--bg-subtle);padding:2px 6px;border-radius:3px;font-size:12px;">{{ $log->event }}</code></td>
                    <td data-label="Subject">
                        @if($log->subject_type)
                            <span style="font-size:12px;color:var(--text-muted);">{{ class_basename($log->subject_type) }}</span>
                            @if($log->subject_id) <span style="color:var(--text-muted);">#{{ $log->subject_id }}</span> @endif
                        @else
                            —
                        @endif
                    </td>
                    <td data-label="Metadata" style="font-size:12px;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        @if($log->metadata)
                            @foreach(array_slice($log->metadata, 0, 3) as $k => $v)
                                <span style="color:var(--text-muted);">{{ $k }}:</span> {{ is_string($v) ? Str::limit($v, 30) : json_encode($v) }}
                            @endforeach
                        @else
                            —
                        @endif
                    </td>
                    <td data-label="IP" style="font-size:12px;color:var(--text-muted);">{{ $log->ip_address }}</td>
                </tr>
                @empty
                <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:2rem;">No audit entries found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links() }}
</div>
@endsection
