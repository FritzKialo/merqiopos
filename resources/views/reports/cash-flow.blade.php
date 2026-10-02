@extends('layouts.app')
@section('title', 'Cash Flow Statement')
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Cash Flow Statement</h1>
            <p class="page-subtitle">{{ \Carbon\Carbon::parse($from)->format('d M Y') }} – {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</p>
        </div>
        <div style="display:flex;gap:0.75rem;align-items:center;">
            <form method="GET" style="display:flex;gap:0.5rem;align-items:center;">
                <input type="date" name="from" value="{{ $from }}" class="form-control" style="width:160px;">
                <span>to</span>
                <input type="date" name="to" value="{{ $to }}" class="form-control" style="width:160px;">
                <button type="submit" class="btn btn-secondary">Filter</button>
            </form>
            <button onclick="window.print()" class="btn btn-primary">Print</button>
        </div>
    </div>

    <div style="max-width:700px;">
        {{-- Operating Activities --}}
        <div class="card" style="padding:1.5rem;margin-bottom:1rem;">
            <h3 style="font-family:Georgia,serif;margin:0 0 1rem;font-size:1rem;border-bottom:2px solid var(--color-border);padding-bottom:0.5rem;">Operating Activities</h3>
            <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                <span>Cash received from sales</span>
                <span style="color:var(--color-success);">KES {{ number_format($cashFromSales, 2) }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                <span>Cash paid for expenses</span>
                <span style="color:var(--color-danger);">(KES {{ number_format($cashExpenses, 2) }})</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:0.6rem 0;font-weight:700;border-top:2px solid var(--color-text);margin-top:0.25rem;">
                <span>Net Operating Cash Flow</span>
                <span style="color:{{ $netOperating >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};">
                    KES {{ number_format($netOperating, 2) }}
                </span>
            </div>
        </div>

        {{-- Investing Activities --}}
        <div class="card" style="padding:1.5rem;margin-bottom:1rem;">
            <h3 style="font-family:Georgia,serif;margin:0 0 1rem;font-size:1rem;border-bottom:2px solid var(--color-border);padding-bottom:0.5rem;">Investing Activities</h3>
            <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                <span>Purchase of assets</span>
                <span style="color:var(--color-danger);">(KES {{ number_format($assetPurchases, 2) }})</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:0.6rem 0;font-weight:700;border-top:2px solid var(--color-text);margin-top:0.25rem;">
                <span>Net Investing Cash Flow</span>
                <span style="color:{{ $netInvesting >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};">
                    KES {{ number_format($netInvesting, 2) }}
                </span>
            </div>
        </div>

        {{-- Financing Activities --}}
        <div class="card" style="padding:1.5rem;margin-bottom:1rem;">
            <h3 style="font-family:Georgia,serif;margin:0 0 1rem;font-size:1rem;border-bottom:2px solid var(--color-border);padding-bottom:0.5rem;">Financing Activities</h3>
            <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                <span>Loan receipts</span>
                <span style="color:var(--color-success);">KES {{ number_format($loanReceipts, 2) }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                <span>Loan repayments</span>
                <span style="color:var(--color-danger);">(KES {{ number_format($loanRepayments, 2) }})</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:0.6rem 0;font-weight:700;border-top:2px solid var(--color-text);margin-top:0.25rem;">
                <span>Net Financing Cash Flow</span>
                <span style="color:{{ $netFinancing >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};">
                    KES {{ number_format($netFinancing, 2) }}
                </span>
            </div>
        </div>

        {{-- Summary --}}
        <div class="card" style="padding:1.5rem;">
            <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                <span style="font-weight:600;">Opening Balance</span>
                <span>KES {{ number_format($openingBalance, 2) }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                <span>Net Cash Change</span>
                <span style="color:{{ $netCashChange >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};">
                    {{ $netCashChange >= 0 ? '+' : '' }}KES {{ number_format($netCashChange, 2) }}
                </span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:0.75rem 0;font-weight:700;font-size:1.1rem;border-top:2px solid var(--color-text);">
                <span>Closing Balance</span>
                <span>KES {{ number_format($closingBalance, 2) }}</span>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .page-header form, .page-header button { display: none; }
    .card { box-shadow: none; border: 1px solid var(--color-border); }
}
</style>
@endsection
