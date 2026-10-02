@extends('layouts.app')
@section('title', 'Audit Log')
@push('styles')
<style>
@media (max-width: 900px) {
    .audit-log-filter-grid { grid-template-columns: 1fr 1fr !important; }
}
@media (max-width: 500px) {
    .audit-log-filter-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h1>Audit Log</h1>
    <a href="{{ route('audit-log.export', request()->query()) }}" class="btn btn-secondary">Export CSV</a>
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card-body">
        <form method="GET" class="audit-log-filter-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr auto;gap:12px;align-items:end;">
            <div>
                <label class="form-label">Action</label>
                <select name="action" class="form-control">
                    <option value="">All</option>
                    @foreach($actions as $a)
                    <option value="{{ $a }}" @selected(request('action')===$a)>{{ ucfirst($a) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Model</label>
                <select name="model" class="form-control">
                    <option value="">All</option>
                    @foreach($models as $m)
                    <option value="{{ $m }}" @selected(request('model')===$m)>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">User</label>
                <select name="user_id" class="form-control">
                    <option value="">All</option>
                    @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected(request('user_id')==$u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">From</label>
                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
            </div>
            <div>
                <label class="form-label">To</label>
                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
            </div>
            <button type="submit" class="btn btn-secondary">Filter</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <table class="table audit-log-table" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:1px solid var(--color-border);">
                    <th style="text-align:left;">Date/Time</th>
                    <th style="text-align:left;">User</th>
                    <th style="text-align:left;">Action</th>
                    <th style="text-align:left;">Description</th>
                    <th style="text-align:left;">Model</th>
                </tr>
            </thead>
            <tbody>
            @forelse($logs as $log)
            @php
                $actionLabel = $log->action ?? $log->event ?? '';
                $desc = $log->summary();
                $modelType = $log->model_type ? class_basename($log->model_type) : (class_basename($log->subject_type ?? '') ?: '');
                $modelId = $log->model_id ?? $log->subject_id ?? null;
                $badgeClasses = ['created'=>'badge-success','updated'=>'badge-blue','deleted'=>'badge-danger','approved'=>'badge-success','paid'=>'badge-purple','sent'=>'badge-blue'];
                $badgeClass = $badgeClasses[$actionLabel] ?? 'badge-secondary';
            @endphp
            <tr style="border-bottom:1px solid var(--color-border);">
                <td data-label="Date/Time" class="text-muted" style="font-size:0.82rem;white-space:nowrap;">{{ $log->created_at->format('d M Y H:i') }}</td>
                <td data-label="User" style="font-size:0.88rem;">{{ $log->user?->name ?? 'System' }}</td>
                <td data-label="Action">
                    <span class="badge {{ $badgeClass }}">{{ strtoupper($actionLabel) }}</span>
                </td>
                <td data-label="Description" style="font-size:0.88rem;">{{ $desc }}</td>
                <td data-label="Model" class="text-muted" style="font-size:0.82rem;">{{ $modelType }}{{ $modelId ? ' #'.$modelId : '' }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-muted" style="padding:40px;text-align:center;">No activity recorded yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div style="margin-top:16px;">
    {{ $logs->links() }}
</div>
@endsection
