@extends('layouts.app')
@section('title', 'Loyalty Points – ' . $customer->name)

@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $customer->name }} — Loyalty Points</h1>
        </div>
        <a href="{{ route('customers.show', $customer) }}" class="btn btn--outline">Back to Customer</a>
    </div>

    {{-- Points balance card --}}
    <div class="kpi-strip" style="margin-bottom:1.5rem;">
        <div class="kpi-item">
            <span class="kpi-value" style="color:var(--color-success);">{{ number_format($customer->loyalty_points, 0) }}</span>
            <span class="kpi-label">Total Points</span>
        </div>
        @if($program)
        <div class="kpi-item">
            <span class="kpi-value">KSh {{ number_format($customer->loyalty_points * $program->redemption_rate, 0) }}</span>
            <span class="kpi-label">Redemption Value</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value">{{ number_format($program->min_redemption_points, 0) }}</span>
            <span class="kpi-label">Minimum to Redeem</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value">{{ number_format($program->points_per_shilling, 2) }} pts</span>
            <span class="kpi-label">Per KSh Spent</span>
        </div>
        @endif
    </div>

    <div class="table-section">
        @if($transactions->isEmpty())
            <div class="empty-state"><h3>No loyalty transactions yet.</h3></div>
        @else
            <div class="table-wrapper">
                <table class="table-responsive-cards">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Points</th>
                            <th>Balance After</th>
                            <th>Description</th>
                            <th>Sale</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $tx)
                        <tr>
                            <td data-label="Date">{{ $tx->created_at->format('d M Y') }}</td>
                            <td data-label="Type">
                                @if($tx->type === 'earn')
                                    <span class="badge badge-success">Earn</span>
                                @elseif($tx->type === 'redeem')
                                    <span class="badge badge-warning">Redeem</span>
                                @elseif($tx->type === 'expire')
                                    <span class="badge badge-danger">Expire</span>
                                @else
                                    <span class="badge badge-secondary">Adjust</span>
                                @endif
                            </td>
                            <td data-label="Points" style="{{ $tx->type === 'earn' ? 'color:var(--color-success);' : 'color:var(--color-danger);' }}">
                                {{ $tx->type === 'earn' ? '+' : '-' }}{{ number_format(abs($tx->points), 0) }}
                            </td>
                            <td data-label="Balance After">{{ number_format($tx->balance_after, 0) }}</td>
                            <td data-label="Description" style="color:var(--color-text-muted); font-size:0.85rem;">{{ $tx->description }}</td>
                            <td data-label="Sale">
                                @if($tx->sale)
                                    <a href="{{ route('sales.show', $tx->sale) }}">{{ $tx->sale->invoice_number }}</a>
                                @else —
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $transactions->links('vendor.pagination.custom') }}
        @endif
    </div>
</div>
@endsection
