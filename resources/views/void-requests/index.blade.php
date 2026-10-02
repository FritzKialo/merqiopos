@extends('layouts.app')
@section('title', 'Pending Voids')

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Pending Voids</h1>
        <p class="page-subtitle">Review void requests submitted by cashiers before a sale is actually cancelled.</p>
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="table-section" style="margin-bottom:1.5rem;">
    <div class="report-section-header" style="margin-bottom:1rem;">
        <h2>Awaiting Approval ({{ $pending->count() }})</h2>
    </div>

    @if($pending->isEmpty())
        <div class="empty-state">
            <h3>Nothing pending.</h3>
            <p>Void requests submitted by cashiers will show up here for approval.</p>
        </div>
    @else
        @foreach($pending as $request)
        <div style="display:flex; gap:1.5rem; flex-wrap:wrap; justify-content:space-between; align-items:flex-start;
                    padding:1rem 0; border-bottom:1px solid var(--color-border);">
            <div style="min-width:220px;">
                <a href="{{ route('sales.show', $request->sale) }}" style="font-weight:600;">{{ $request->sale->invoice_number }}</a>
                <div style="font-size:0.85rem;color:var(--color-text-muted);">
                    KSh {{ number_format($request->sale->total_amount, 2) }} &middot; requested by {{ $request->requestedBy->name ?? $request->requestedBy->username ?? 'Unknown' }}
                </div>
                <div style="font-size:0.8rem;color:var(--color-text-muted);">{{ $request->created_at->diffForHumans() }}</div>
            </div>
            <div style="flex:1;min-width:200px;">
                <div style="font-size:0.9rem;"><strong>Reason:</strong> {{ $request->voidReason->name ?? '—' }}</div>
                @if($request->note)
                <div style="font-size:0.85rem;color:var(--color-text-muted);margin-top:2px;">{{ $request->note }}</div>
                @endif
            </div>
            <div style="display:flex;gap:8px;">
                <form method="POST" action="{{ route('void-requests.approve', $request) }}"
                      onsubmit="return confirm('Approve — this will cancel sale {{ addslashes($request->sale->invoice_number) }} and restore stock.')">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn--primary" style="background:var(--color-success);border-color:var(--color-success);">Approve</button>
                </form>
                <form method="POST" action="{{ route('void-requests.reject', $request) }}"
                      onsubmit="return confirm('Reject this void request? The sale stays active.')">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn--danger">Reject</button>
                </form>
            </div>
        </div>
        @endforeach
    @endif
</div>

<div class="table-section">
    <div class="report-section-header" style="margin-bottom:1rem;">
        <h2>Recently Reviewed</h2>
    </div>

    @if($recent->isEmpty())
        <div class="empty-state">
            <p>No reviewed void requests yet.</p>
        </div>
    @else
    <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;">
        <thead><tr style="border-bottom:1px solid var(--color-border);background:var(--color-surface-2);">
            <th style="text-align:left;padding:8px 12px;">Sale</th>
            <th style="text-align:left;padding:8px 12px;">Reason</th>
            <th style="text-align:left;padding:8px 12px;">Requested By</th>
            <th style="text-align:center;padding:8px 12px;">Status</th>
            <th style="text-align:left;padding:8px 12px;">Reviewed By</th>
            <th style="text-align:left;padding:8px 12px;">When</th>
        </tr></thead>
        <tbody>
        @foreach($recent as $request)
        <tr style="border-bottom:1px solid var(--color-border);">
            <td data-label="Sale" style="padding:8px 12px;"><a href="{{ route('sales.show', $request->sale) }}">{{ $request->sale->invoice_number ?? '—' }}</a></td>
            <td data-label="Reason" style="padding:8px 12px;">{{ $request->voidReason->name ?? '—' }}</td>
            <td data-label="Requested By" style="padding:8px 12px;">{{ $request->requestedBy->name ?? $request->requestedBy->username ?? '—' }}</td>
            <td data-label="Status" style="padding:8px 12px;text-align:center;">
                @if($request->status === 'approved')<span class="badge badge-success">Approved</span>
                @else<span class="badge badge-danger">Rejected</span>@endif
            </td>
            <td data-label="Reviewed By" style="padding:8px 12px;">{{ $request->reviewedBy->name ?? $request->reviewedBy->username ?? '—' }}</td>
            <td data-label="When" style="padding:8px 12px;font-size:0.85rem;color:var(--color-text-muted);">{{ $request->reviewed_at?->format('d M Y, H:i') }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    @endif
</div>

</div>
@endsection
