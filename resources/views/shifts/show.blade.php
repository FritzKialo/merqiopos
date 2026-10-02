@extends('layouts.app')
@section('title', 'Shift Details')
@push('styles')
<style>
/* .form-grid-2's own mobile rule has no !important, so it never actually
   overrode this element's own inline grid-template-columns — inline always
   wins over an external rule for the same property, media query or not. */
@media (max-width: 768px) {
    .form-grid-2 { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Shift — {{ $shift->opened_at->format('d M Y') }}</h1>
            <p class="page-subtitle">
                @if($shift->isOpen())
                    <span class="badge badge--green">Open</span>
                @else
                    <span class="badge badge--gray">Closed</span>
                @endif
            </p>
        </div>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            @if($shift->isOpen())
                <a href="{{ route('shifts.close', $shift) }}" class="btn btn--danger">Close Shift</a>
            @endif
            <a href="{{ route('shifts.index') }}" class="btn btn--outline">Back</a>
        </div>
    </div>

    @if(session('success')) <div class="alert alert--success">{{ session('success') }}</div> @endif

    <div class="form-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
        <div class="table-card">
            <div class="card-body">
                <h3 style="margin-bottom:1rem;">Shift Info</h3>
                <table class="table-plain" style="width:100%;border-collapse:collapse;">
                    <tr><td style="padding:.4rem 0;color:var(--text-muted);">Opened by</td><td>{{ $shift->opener?->name }}</td></tr>
                    <tr><td style="padding:.4rem 0;color:var(--text-muted);">Opened at</td><td>{{ $shift->opened_at->format('d M Y H:i') }}</td></tr>
                    <tr><td style="padding:.4rem 0;color:var(--text-muted);">Opening Float</td><td>KSh {{ number_format($shift->opening_float, 0) }}</td></tr>
                    @if(! $shift->isOpen())
                    <tr><td style="padding:.4rem 0;color:var(--text-muted);">Closed by</td><td>{{ $shift->closer?->name }}</td></tr>
                    <tr><td style="padding:.4rem 0;color:var(--text-muted);">Closed at</td><td>{{ $shift->closed_at->format('d M Y H:i') }}</td></tr>
                    @endif
                    @if($shift->notes)
                    <tr><td style="padding:.4rem 0;color:var(--text-muted);">Notes</td><td>{{ $shift->notes }}</td></tr>
                    @endif
                </table>
            </div>
        </div>

        @if(! $shift->isOpen())
        <div class="table-card">
            <div class="card-body">
                <h3 style="margin-bottom:1rem;">Sales Summary</h3>
                <table class="table-plain" style="width:100%;border-collapse:collapse;">
                    <tr><td style="padding:.4rem 0;color:var(--text-muted);">Total Transactions</td><td>{{ $shift->total_transactions }}</td></tr>
                    <tr><td style="padding:.4rem 0;color:var(--text-muted);">Total Sales</td><td>KSh {{ number_format($shift->total_sales, 0) }}</td></tr>
                    <tr><td style="padding:.4rem 0;color:var(--text-muted);">Cash Sales</td><td>KSh {{ number_format($shift->total_cash_sales, 0) }}</td></tr>
                    <tr><td style="padding:.4rem 0;color:var(--text-muted);">M-Pesa Sales</td><td>KSh {{ number_format($shift->total_mpesa_sales, 0) }}</td></tr>
                    <tr><td style="padding:.4rem 0;color:var(--text-muted);">Card Sales</td><td>KSh {{ number_format($shift->total_card_sales, 0) }}</td></tr>
                    <tr><td style="padding:.4rem 0;color:var(--text-muted);">Expected Cash</td><td>KSh {{ number_format($shift->expected_cash, 0) }}</td></tr>
                    <tr><td style="padding:.4rem 0;color:var(--text-muted);">Actual Cash</td><td>KSh {{ number_format($shift->closing_cash, 0) }}</td></tr>
                    <tr>
                        <td style="padding:.4rem 0;color:var(--text-muted);">Variance</td>
                        <td style="color:{{ $shift->cash_variance >= 0 ? 'var(--green)' : 'var(--danger)' }};font-weight:600;">
                            {{ $shift->cash_variance >= 0 ? '+' : '' }}KSh {{ number_format($shift->cash_variance, 0) }}
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        @endif
    </div>

    <div class="table-card" style="margin-top:1.5rem;">
        <div class="card-body">
            <h3 style="margin-bottom:1rem;">Sales During This Shift ({{ $sales->count() }})</h3>
            @if($sales->isEmpty())
                <p style="color:var(--color-text-muted, var(--text-muted));">No sales recorded against this shift yet.</p>
            @else
                <div class="table-wrap">
                <table class="table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Payment Method</th>
                            <th>Amount</th>
                            <th>Time</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sales as $sale)
                        <tr>
                            <td data-label="Invoice #">{{ $sale->invoice_number }}</td>
                            <td data-label="Customer">{{ $sale->customer?->name ?? 'Walk-in' }}</td>
                            <td data-label="Payment Method">{{ ucfirst($sale->payment_method) }}</td>
                            <td data-label="Amount">KSh {{ number_format($sale->total_amount, 2) }}</td>
                            <td data-label="Time">{{ $sale->created_at->format('H:i') }}</td>
                            <td data-label="">
                                <a href="{{ route('sales.show', $sale) }}" class="btn btn--outline btn--sm">View</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
