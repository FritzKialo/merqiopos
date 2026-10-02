@extends('layouts.app')
@section('title', 'Close Shift')
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
            <h1 class="page-title">Close Shift</h1>
            <p class="page-subtitle">Opened {{ $shift->opened_at->format('d M Y H:i') }}</p>
        </div>
        <a href="{{ route('shifts.show', $shift) }}" class="btn btn--outline">Cancel</a>
    </div>

    @if($errors->any())
        <div class="alert alert--error">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
    @endif

    <div class="form-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
        {{-- Sales Summary --}}
        <div class="table-card">
            <div class="card-body">
                <h3 style="margin-bottom:1rem;">Sales This Shift</h3>
                <table class="table-plain" style="width:100%;border-collapse:collapse;">
                    <tr><td style="padding:.5rem 0;color:var(--text-muted);">Total Transactions</td><td style="text-align:right;"><strong>{{ $totalTransactions }}</strong></td></tr>
                    <tr><td style="padding:.5rem 0;color:var(--text-muted);">Total Sales</td><td style="text-align:right;"><strong>KSh {{ number_format($totalSales, 0) }}</strong></td></tr>
                    <tr><td style="padding:.5rem 0;color:var(--text-muted);">Cash Sales</td><td style="text-align:right;">KSh {{ number_format($cashSales, 0) }}</td></tr>
                    <tr><td style="padding:.5rem 0;color:var(--text-muted);">M-Pesa Sales</td><td style="text-align:right;">KSh {{ number_format($mpesaSales, 0) }}</td></tr>
                    <tr><td style="padding:.5rem 0;color:var(--text-muted);">Card Sales</td><td style="text-align:right;">KSh {{ number_format($cardSales, 0) }}</td></tr>
                    <tr style="border-top:2px solid var(--border);"><td style="padding:.5rem 0;color:var(--text-muted);">Opening Float</td><td style="text-align:right;">KSh {{ number_format($shift->opening_float, 0) }}</td></tr>
                    <tr><td style="padding:.5rem 0;"><strong>Expected Cash in Till</strong></td><td style="text-align:right;"><strong>KSh {{ number_format($expectedCash, 0) }}</strong></td></tr>
                </table>
            </div>
        </div>

        {{-- Closing form --}}
        <div class="table-card">
            <div class="card-body">
                <h3 style="margin-bottom:1rem;">Count Your Cash</h3>
                <form method="POST" action="{{ route('shifts.close.store', $shift) }}">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">Actual Cash in Till (KSh) *</label>
                        <input type="number" name="closing_cash" class="form-control"
                               value="{{ old('closing_cash') }}" min="0" step="0.01" required autofocus
                               placeholder="Count the physical cash…">
                        <span class="form-hint">Expected: KSh {{ number_format($expectedCash, 0) }}</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes', $shift->notes) }}</textarea>
                    </div>
                    <button type="submit" class="btn btn--danger" style="width:100%;">Close Shift</button>
                </form>
            </div>
        </div>
    </div>

    @if($business->enable_digital_float && $floatBreakdown->isNotEmpty())
    <div class="table-card" style="margin-top:1.5rem;">
        <div class="card-body">
            <h3 style="margin-bottom:1rem;">Cashier Float Settlement</h3>
            <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;">
                <thead><tr style="border-bottom:1px solid var(--color-border);background:var(--color-surface-2);">
                    <th style="text-align:left;padding:8px 12px;">Cashier</th>
                    <th style="text-align:right;padding:8px 12px;">Cash Sales</th>
                    <th style="text-align:right;padding:8px 12px;">Deposits</th>
                    <th style="text-align:right;padding:8px 12px;">Amount Owed</th>
                    <th style="text-align:right;padding:8px 12px;">Shortfall</th>
                </tr></thead>
                <tbody>
                @foreach($floatBreakdown as $row)
                <tr style="border-bottom:1px solid var(--color-border);">
                    <td data-label="Cashier" style="padding:8px 12px;">{{ $row['user']->name }}</td>
                    <td data-label="Cash Sales" style="padding:8px 12px;text-align:right;">KSh {{ number_format($row['cash_sales'], 2) }}</td>
                    <td data-label="Deposits" style="padding:8px 12px;text-align:right;">KSh {{ number_format($row['deposits'], 2) }}</td>
                    <td data-label="Amount Owed" style="padding:8px 12px;text-align:right;">KSh {{ number_format($row['amount_owed'], 2) }}</td>
                    <td data-label="Shortfall" style="padding:8px 12px;text-align:right;font-weight:600;color:{{ $row['shortfall'] > 0 ? 'var(--color-danger)' : 'var(--color-success)' }};">
                        KSh {{ number_format($row['shortfall'], 2) }}
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            <p style="margin-top:0.75rem;font-size:0.8rem;color:var(--color-text-muted);">
                A cashier still shows an amount owed here if they haven't deposited their cash sales yet —
                record it under <a href="{{ route('cash-deposits.index') }}">Finance &gt; Cash Deposits</a> before closing.
            </p>
        </div>
    </div>
    @endif
</div>
@endsection
