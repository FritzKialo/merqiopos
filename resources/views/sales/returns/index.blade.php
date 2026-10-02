@extends('layouts.app')
@section('title', 'Sale Returns')
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Sale Returns</h1>
            <p class="page-subtitle">Manage customer returns and refunds</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert--success">{{ session('success') }}</div>
    @endif

    <div class="table-card">
        <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Return #</th>
                    <th>Original Sale</th>
                    <th>Customer</th>
                    <th>Refund Method</th>
                    <th>Stock Action</th>
                    <th>Total Refund</th>
                    <th>Date</th>
                    <th>By</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($returns as $return)
                <tr>
                    <td data-label="Return #"><strong>{{ $return->return_number }}</strong></td>
                    <td data-label="Original Sale">
                        @if($return->sale)
                            <a href="{{ route('sales.show', $return->sale) }}">{{ $return->sale->invoice_number }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td data-label="Customer">{{ $return->customer?->name ?? '—' }}</td>
                    <td data-label="Refund Method">{{ ucwords(str_replace("_", " ", $return->refund_method)) }}</td>
                    <td data-label="Stock Action">
                        @if($return->stock_action === 'restock')
                            <span class="badge badge--green">Restocked</span>
                        @else
                            <span class="badge badge--red">Write-off</span>
                        @endif
                    </td>
                    <td data-label="Total Refund">KSh {{ number_format($return->total_refund, 0) }}</td>
                    <td data-label="Date">{{ $return->created_at->format('d M Y') }}</td>
                    <td data-label="By">{{ $return->user?->name }}</td>
                    <td data-label="View"><a href="{{ route('returns.show', $return) }}" class="btn btn--outline btn--sm">View</a></td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" style="text-align:center;color:var(--text-muted);padding:2rem;">No returns recorded yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    {{ $returns->links() }}
</div>
@endsection
