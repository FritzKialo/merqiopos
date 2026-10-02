@extends('layouts.app')
@section('title', 'Balance Sheet')
@push('styles')
<style>
@media (max-width: 700px) {
    .bs-columns-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header" style="flex-wrap:wrap;gap:0.75rem;">
        <div>
            <h1 class="page-title">Balance Sheet</h1>
            <p class="page-subtitle">As at {{ \Carbon\Carbon::parse($asAt)->format('d M Y') }}</p>
        </div>
        <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
            <form method="GET" style="display:flex;gap:0.5rem;align-items:center;">
                <input type="date" name="date" value="{{ $asAt }}" class="form-control" style="width:180px;">
                <button type="submit" class="btn btn-secondary">Update</button>
            </form>
            <button onclick="window.print()" class="btn btn-primary">Print</button>
        </div>
    </div>

    <div class="bs-columns-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
        {{-- ASSETS --}}
        <div class="card" style="padding:1.5rem;">
            <h3 style="font-family:Georgia,serif;margin:0 0 1rem;font-size:1.1rem;border-bottom:2px solid var(--color-border);padding-bottom:0.5rem;">ASSETS</h3>

            <div style="margin-bottom:1rem;">
                <p style="font-weight:600;font-size:0.8rem;text-transform:uppercase;color:var(--color-text-muted);margin:0 0 0.5rem;">Current Assets</p>
                <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                    <span>Cash & Bank</span>
                    <span>KES {{ number_format($cash, 2) }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                    <span>Accounts Receivable</span>
                    <span>KES {{ number_format($accountsReceivable, 2) }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                    <span>Inventory</span>
                    <span>KES {{ number_format($inventory, 2) }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:0.6rem 0;font-weight:600;border-top:1px solid var(--color-text);margin-top:0.25rem;">
                    <span>Total Current Assets</span>
                    <span>KES {{ number_format($cash + $accountsReceivable + $inventory, 2) }}</span>
                </div>
            </div>

            <div style="margin-bottom:1rem;">
                <p style="font-weight:600;font-size:0.8rem;text-transform:uppercase;color:var(--color-text-muted);margin:0 0 0.5rem;">Fixed Assets</p>
                <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                    <span>Property, Plant & Equipment</span>
                    <span>KES {{ number_format($fixedAssets, 2) }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:0.6rem 0;font-weight:600;border-top:1px solid var(--color-text);margin-top:0.25rem;">
                    <span>Total Fixed Assets</span>
                    <span>KES {{ number_format($fixedAssets, 2) }}</span>
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;padding:0.75rem 0;font-weight:700;font-size:1.05rem;border-top:2px solid var(--color-text);">
                <span>TOTAL ASSETS</span>
                <span>KES {{ number_format($totalAssets, 2) }}</span>
            </div>
        </div>

        {{-- LIABILITIES & EQUITY --}}
        <div class="card" style="padding:1.5rem;">
            <h3 style="font-family:Georgia,serif;margin:0 0 1rem;font-size:1.1rem;border-bottom:2px solid var(--color-border);padding-bottom:0.5rem;">LIABILITIES & EQUITY</h3>

            <div style="margin-bottom:1rem;">
                <p style="font-weight:600;font-size:0.8rem;text-transform:uppercase;color:var(--color-text-muted);margin:0 0 0.5rem;">Current Liabilities</p>
                <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                    <span>Accounts Payable</span>
                    <span>KES {{ number_format($accountsPayable, 2) }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:0.6rem 0;font-weight:600;border-top:1px solid var(--color-text);margin-top:0.25rem;">
                    <span>Total Current Liabilities</span>
                    <span>KES {{ number_format($accountsPayable, 2) }}</span>
                </div>
            </div>

            <div style="margin-bottom:1rem;">
                <p style="font-weight:600;font-size:0.8rem;text-transform:uppercase;color:var(--color-text-muted);margin:0 0 0.5rem;">Long-term Liabilities</p>
                <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                    <span>Loans Payable</span>
                    <span>KES {{ number_format($loansOutstanding, 2) }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:0.6rem 0;font-weight:600;border-top:1px solid var(--color-text);margin-top:0.25rem;">
                    <span>Total Long-term Liabilities</span>
                    <span>KES {{ number_format($loansOutstanding, 2) }}</span>
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;padding:0.6rem 0;font-weight:600;border-top:1px solid var(--color-text);margin-bottom:1rem;">
                <span>TOTAL LIABILITIES</span>
                <span>KES {{ number_format($totalLiabilities, 2) }}</span>
            </div>

            <div style="margin-bottom:1rem;">
                <p style="font-weight:600;font-size:0.8rem;text-transform:uppercase;color:var(--color-text-muted);margin:0 0 0.5rem;">Equity</p>
                <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid var(--color-border);">
                    <span>Owner's Equity (Net Assets)</span>
                    <span style="color:{{ $equity >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};">
                        KES {{ number_format($equity, 2) }}
                    </span>
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;padding:0.75rem 0;font-weight:700;font-size:1.05rem;border-top:2px solid var(--color-text);">
                <span>TOTAL LIABILITIES & EQUITY</span>
                <span>KES {{ number_format($totalLiabilities + $equity, 2) }}</span>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .page-header button, .page-header form { display: none; }
    .card { box-shadow: none; border: 1px solid var(--color-border); }
}
</style>
@endsection
