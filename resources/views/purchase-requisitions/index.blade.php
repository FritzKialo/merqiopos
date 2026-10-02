@extends('layouts.app')
@section('title', 'Purchase Requisitions')
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Purchase Requisitions</h1>
            <p class="page-subtitle">Manage internal purchase requests</p>
        </div>
        <a href="{{ route('purchase-requisitions.create') }}" class="btn btn--primary">+ New Requisition</a>
    </div>

    @if(session('success'))
        <div class="alert alert--success">{{ session('success') }}</div>
    @endif

    {{-- Status Filter --}}
    <div style="display:flex;gap:8px;margin-bottom:1.5rem;flex-wrap:wrap;">
        <a href="{{ route('purchase-requisitions.index') }}" class="btn btn--sm {{ !request('status') ? 'btn--primary' : 'btn--outline' }}">All</a>
        @foreach($statuses as $s)
            <a href="{{ route('purchase-requisitions.index', ['status' => $s]) }}" class="btn btn--sm {{ request('status')==$s ? 'btn--primary' : 'btn--outline' }}">{{ ucfirst($s) }}</a>
        @endforeach
    </div>

    <div class="table-card">
        <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Urgency</th>
                    <th>Status</th>
                    <th>Requested By</th>
                    <th>Required By</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($requisitions as $pr)
                <tr>
                    <td data-label="Reference"><strong>{{ $pr->reference }}</strong></td>
                    <td data-label="Urgency">
                        @if($pr->urgency === 'urgent')
                            <span class="badge badge-danger">Urgent</span>
                        @elseif($pr->urgency === 'low')
                            <span class="badge badge-secondary">Low</span>
                        @else
                            <span class="badge badge-info">Normal</span>
                        @endif
                    </td>
                    <td data-label="Status">
                        @if($pr->status === 'approved')
                            <span class="badge badge-success">Approved</span>
                        @elseif($pr->status === 'rejected')
                            <span class="badge badge-danger">Rejected</span>
                        @elseif($pr->status === 'converted')
                            <span class="badge badge-secondary">Converted</span>
                        @else
                            <span class="badge badge-warning">Pending</span>
                        @endif
                    </td>
                    <td data-label="Requested By">{{ $pr->requestedBy ? $pr->requestedBy->name : '—' }}</td>
                    <td data-label="Required By">{{ $pr->required_by ? $pr->required_by->format('d M Y') : '—' }}</td>
                    <td data-label="Date">{{ $pr->created_at->format('d M Y') }}</td>
                    <td data-label="Actions">
                        <a href="{{ route('purchase-requisitions.show', $pr) }}" class="btn btn--outline btn--sm">View</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--color-text-muted);">No requisitions found.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    {{ $requisitions->links('vendor.pagination.custom') }}
</div>
@endsection
