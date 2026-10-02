@extends('layouts.app')
@section('title', 'Stock Counts')
@push('styles')
<style>
@media (min-width: 769px) {
    .stock-counts-table th, .stock-counts-table td { padding: 12px 16px; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h1>Physical Stock Counts</h1>
    <a href="{{ route('inventory.stock-counts.create') }}" class="btn btn-primary">+ Start New Count</a>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
<div class="card"><div class="card-body" style="padding:0;">
<div class="table-wrapper">
<table class="stock-counts-table" style="width:100%;border-collapse:collapse;">
<thead><tr style="border-bottom:1px solid var(--color-border);">
    <th style="text-align:left;">Reference</th>
    <th style="text-align:left;">Started By</th>
    <th style="text-align:left;">Started</th>
    <th style="text-align:left;">Completed</th>
    <th style="text-align:left;">Status</th>
    <th style="text-align:right;">Actions</th>
</tr></thead>
<tbody>
@forelse($counts as $c)
<tr style="border-bottom:1px solid var(--color-border);">
    <td data-label="Reference" style="font-family:monospace;">{{ $c->reference }}</td>
    <td data-label="Started By">{{ $c->user?->name ?? '—' }}</td>
    <td data-label="Started">{{ $c->started_at?->format('d M Y H:i') ?? '—' }}</td>
    <td data-label="Completed">{{ $c->completed_at?->format('d M Y H:i') ?? '—' }}</td>
    <td data-label="Status">
        @php $badgeClass=['draft'=>'badge-secondary','in_progress'=>'badge-blue','completed'=>'badge-success','cancelled'=>'badge-danger'][$c->status]??'badge-secondary'; @endphp
        <span class="badge {{ $badgeClass }}">{{ ucfirst(str_replace('_',' ',$c->status)) }}</span>
    </td>
    <td data-label="Actions" style="text-align:right;">
        <a href="{{ route('inventory.stock-counts.show', $c) }}" class="btn btn-secondary" style="padding:4px 10px;font-size:0.8rem;">{{ $c->status==='in_progress'?'Enter Counts':'View' }}</a>
        @if(in_array($c->status, ['draft','cancelled']))
        <form method="POST" action="{{ route('inventory.stock-counts.destroy', $c) }}" style="display:inline;"
              onsubmit="return confirm('Delete stock count {{ addslashes($c->reference) }}?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger" style="padding:4px 10px;font-size:0.8rem;">Delete</button>
        </form>
        @endif
    </td>
</tr>
@empty
<tr><td colspan="6" class="text-muted" style="padding:40px;text-align:center;">No stock counts yet.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div></div>
{{ $counts->links() }}
@endsection
