@extends('layouts.app')
@section('title', 'Expense Claims')
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Expense Claims</h1>
            <p class="page-subtitle">Staff reimbursement requests</p>
        </div>
        <a href="{{ route('expense-claims.create') }}" class="btn btn--primary">+ New Claim</a>
    </div>

    @if(session('success'))
        <div class="alert alert--success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert--danger">{{ session('error') }}</div>
    @endif

    <div class="table-card">
        <table class="table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Title</th>
                    <th>Submitted By</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($claims as $claim)
                <tr>
                    <td data-label="Reference"><strong>{{ $claim->reference }}</strong></td>
                    <td data-label="Title">{{ $claim->title }}</td>
                    <td data-label="Submitted By">{{ $claim->user ? $claim->user->name : '—' }}</td>
                    <td data-label="Amount"><strong>KSh {{ number_format($claim->total_amount, 2) }}</strong></td>
                    <td data-label="Status">
                        @php
                            $cls = match($claim->status) {
                                'approved' => 'badge-success',
                                'paid'     => 'badge-success',
                                'rejected' => 'badge-danger',
                                'submitted'=> 'badge-warning',
                                default    => 'badge-secondary',
                            };
                        @endphp
                        <span class="badge {{ $cls }}">{{ ucfirst($claim->status) }}</span>
                    </td>
                    <td data-label="Date">{{ $claim->submitted_at ? $claim->submitted_at->format('d M Y') : $claim->created_at->format('d M Y') }}</td>
                    <td data-label="">
                        <a href="{{ route('expense-claims.show', $claim) }}" class="btn btn--outline btn--sm">View</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--color-text-muted);">No expense claims found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $claims->links('vendor.pagination.custom') }}
</div>
@endsection
