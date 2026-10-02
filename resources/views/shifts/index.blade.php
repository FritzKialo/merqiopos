@extends('layouts.app')
@section('title', 'Shifts')
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Shift Management</h1>
            <p class="page-subtitle">Track opening and closing of business shifts</p>
        </div>
        @if($openShift)
            <a href="{{ route('shifts.close', $openShift) }}" class="btn btn--danger">Close Current Shift</a>
        @else
            <a href="{{ route('shifts.open') }}" class="btn btn--primary">Open Shift</a>
        @endif
    </div>

    @if(session('success')) <div class="alert alert--success">{{ session('success') }}</div> @endif
    @if(session('error'))   <div class="alert alert--error">{{ session('error') }}</div> @endif

    @if($openShift)
    <div class="table-card" style="margin-bottom:1.5rem;border-left:4px solid var(--green);">
        <div class="card-body">
            <strong style="color:var(--green);">Shift Currently Open</strong> &mdash;
            Opened {{ $openShift->opened_at->format('d M Y H:i') }} by {{ $openShift->opener?->name }}
            &mdash; Float: KSh {{ number_format($openShift->opening_float, 0) }}
            &nbsp;<a href="{{ route('shifts.show', $openShift) }}" class="btn btn--outline btn--sm">View</a>
        </div>
    </div>
    @endif

    <div class="table-card">
        <table class="table">
            <thead>
                <tr>
                    <th>Opened</th>
                    <th>Opened By</th>
                    <th>Float</th>
                    <th>Total Sales</th>
                    <th>Cash</th>
                    <th>Variance</th>
                    <th>Status</th>
                    <th>Closed</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($shifts as $shift)
                <tr>
                    <td data-label="Opened">{{ $shift->opened_at->format('d M Y H:i') }}</td>
                    <td data-label="Opened By">{{ $shift->opener?->name }}</td>
                    <td data-label="Float">KSh {{ number_format($shift->opening_float, 0) }}</td>
                    <td data-label="Total Sales">{{ $shift->total_sales ? 'KSh '.number_format($shift->total_sales, 0) : '—' }}</td>
                    <td data-label="Cash">{{ $shift->total_cash_sales ? 'KSh '.number_format($shift->total_cash_sales, 0) : '—' }}</td>
                    <td data-label="Variance">
                        @if($shift->cash_variance !== null)
                            <span style="color:{{ $shift->cash_variance >= 0 ? 'var(--green)' : 'var(--danger)' }}">
                                {{ $shift->cash_variance >= 0 ? '+' : '' }}KSh {{ number_format($shift->cash_variance, 0) }}
                            </span>
                        @else
                            —
                        @endif
                    </td>
                    <td data-label="Status">
                        @if($shift->isOpen())
                            <span class="badge badge--green">Open</span>
                        @else
                            <span class="badge badge--gray">Closed</span>
                        @endif
                    </td>
                    <td data-label="Closed">{{ $shift->closed_at ? $shift->closed_at->format('H:i') : '—' }}</td>
                    <td data-label="">
                        <a href="{{ route('shifts.show', $shift) }}" class="btn btn--outline btn--sm">View</a>
                        @if($shift->isOpen())
                            <a href="{{ route('shifts.close', $shift) }}" class="btn btn--danger btn--sm">Close</a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:2rem;">No shifts recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $shifts->links() }}
</div>
@endsection
