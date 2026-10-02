@extends('layouts.app')
@section('title', 'Cash Reconciliation')
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Cash Reconciliation</h1>
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
        {{-- Sales & Voids --}}
        <div class="card" style="padding:1.5rem;margin-bottom:1rem;">
            <h3 style="font-family:Georgia,serif;margin:0 0 1rem;font-size:1rem;border-bottom:2px solid var(--color-border);padding-bottom:0.5rem;">Sales</h3>
            <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                <span>Gross Sales</span>
                <span>KES {{ number_format($grossSales, 2) }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                <span>Voided Sales</span>
                <span style="color:var(--color-danger);">(KES {{ number_format($voidedSales, 2) }})</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:0.6rem 0;font-weight:700;border-top:2px solid var(--color-text);margin-top:0.25rem;">
                <span>Net Sales</span>
                <span>KES {{ number_format($netSales, 2) }}</span>
            </div>
        </div>

        {{-- Payment Mix --}}
        <div class="card" style="padding:1.5rem;margin-bottom:1rem;">
            <h3 style="font-family:Georgia,serif;margin:0 0 1rem;font-size:1rem;border-bottom:2px solid var(--color-border);padding-bottom:0.5rem;">Net Sales, By Payment Method</h3>
            <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                <span>Cash</span>
                <span>KES {{ number_format($cashCollected, 2) }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                <span>M-Pesa</span>
                <span>KES {{ number_format($mpesaCollected, 2) }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                <span>Bank Transfer</span>
                <span>KES {{ number_format($bankTransferCollected, 2) }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                <span>Outstanding (Not Yet Collected)</span>
                <span style="color:var(--color-danger);">(KES {{ number_format($outstanding, 2) }})</span>
            </div>
        </div>

        {{-- Cash-in-Till Reconciliation --}}
        <div class="card" style="padding:1.5rem;margin-bottom:1rem;">
            <h3 style="font-family:Georgia,serif;margin:0 0 1rem;font-size:1rem;border-bottom:2px solid var(--color-border);padding-bottom:0.5rem;">Cash-in-Till Reconciliation</h3>
            <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                <span>Opening Floats + Cash Sales <span style="color:var(--color-text-muted);font-size:0.8rem;">({{ $shiftCount }} shift{{ $shiftCount === 1 ? '' : 's' }} closed)</span></span>
                <span>KES {{ number_format($shiftExpectedCash, 2) }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                <span>Cash Expenses</span>
                <span style="color:var(--color-danger);">(KES {{ number_format($cashExpenses, 2) }})</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:0.6rem 0;font-weight:700;border-top:2px solid var(--color-text);margin-top:0.25rem;">
                <span>Expected Cash In Tills</span>
                <span>KES {{ number_format($expectedCashInTills, 2) }}</span>
            </div>
        </div>

        {{-- Variance --}}
        <div class="card" style="padding:1.5rem;">
            <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                <span style="font-weight:600;">Actual Cash Counted</span>
                <span>KES {{ number_format($actualCashCounted, 2) }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:0.75rem 0;font-weight:700;font-size:1.1rem;border-top:2px solid var(--color-text);">
                <span>Variance</span>
                <span style="color:{{ abs($variance) < 0.01 ? 'var(--color-success)' : 'var(--color-danger)' }};">
                    {{ $variance >= 0 ? '+' : '' }}KES {{ number_format($variance, 2) }}
                </span>
            </div>
            @if($shiftCount === 0)
            <p style="margin-top:1rem;font-size:0.85rem;color:var(--color-text-muted);">No shifts were closed in this period — cash-in-till figures are KES 0 until at least one shift is closed via Sales &gt; Shifts.</p>
            @elseif(abs($variance) >= 0.01)
            <p style="margin-top:1rem;font-size:0.85rem;color:var(--color-text-muted);">A non-zero variance means physical cash didn't match what the tills should hold. Common causes: an unrecorded cash expense, a miscounted float, or a shift closed with the wrong amount entered.</p>
            @endif
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
