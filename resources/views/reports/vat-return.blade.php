@extends('layouts.app')
@section('title', 'VAT Return Summary')
@push('styles')
<style>
@media (max-width: 700px) {
    .vat-stats-grid { grid-template-columns: 1fr 1fr !important; }
}
@media (max-width: 420px) {
    .vat-stats-grid { grid-template-columns: 1fr !important; }
}
@media (max-width: 900px) {
    .vat-tables-grid { grid-template-columns: 1fr !important; }
}
@media (min-width: 769px) {
    .vat-table th, .vat-table td { padding: 8px; }
}
</style>
@endpush
@section('content')
<div class="page">

<div class="page-header">
    <h1 class="page-title">VAT Return Summary</h1>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <select name="month" class="form-control" style="width:130px;">
                @for($m=1;$m<=12;$m++)<option value="{{ $m }}" @selected($m==$month)>{{ date('F',mktime(0,0,0,$m,1)) }}</option>@endfor
            </select>
            <select name="year" class="form-control" style="width:100px;">
                @for($y=now()->year;$y>=now()->year-3;$y--)<option value="{{ $y }}" @selected($y==$year)>{{ $y }}</option>@endfor
            </select>
            <button type="submit" class="btn btn-secondary">View</button>
        </form>
        {{-- reports.vat-return.export doesn't exist as a separate route — the
        controller handles CSV export via ?export=csv on this same route. The
        old name here made route() throw on every single page load, not just
        on export click. --}}
        <a href="{{ route('reports.vat-return', ['month'=>$month,'year'=>$year,'export'=>'csv']) }}" class="btn btn-primary">Export CSV</a>
    </div>
</div>

@if(!$business->vat_registered ?? false)
<div class="alert alert-warning" style="margin-bottom:16px;">
Your business is not marked as VAT-registered. VAT figures may be estimates. Update in Settings &rarr; VAT.
</div>
@endif

<div class="vat-stats-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:24px;">
    <div class="stat-card"><div class="stat-label">Output VAT (Collected)</div><div class="stat-value">KES {{ number_format($outputVat, 2) }}</div></div>
    <div class="stat-card"><div class="stat-label">Input VAT (Paid on Purchases)</div><div class="stat-value">KES {{ number_format($inputVat, 2) }}</div></div>
    <div class="stat-card" style="border-left:4px solid {{ $netVat >= 0 ? 'var(--color-danger)' : 'var(--color-success)' }};">
        <div class="stat-label">Net VAT {{ $netVat >= 0 ? 'Payable to KRA' : 'Refundable' }}</div>
        <div class="stat-value {{ $netVat >= 0 ? 'text-danger' : 'text-success' }}">KES {{ number_format(abs($netVat), 2) }}</div>
    </div>
</div>

<div class="vat-tables-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
<div class="card">
<div class="card-body">
<h3 class="text-danger" style="margin:0 0 16px;">Output VAT — Tax Collected</h3>
<table class="vat-table" style="width:100%;border-collapse:collapse;">
<thead><tr style="border-bottom:2px solid var(--color-border);"><th style="text-align:left;">Source</th><th style="text-align:right;">Count</th><th style="text-align:right;">Gross</th><th style="text-align:right;">VAT</th></tr></thead>
<tbody>
<tr style="border-bottom:1px solid var(--color-border);"><td data-label="Source">POS Sales</td><td data-label="Count" style="text-align:right;">{{ $salesVat->count ?? 0 }}</td><td data-label="Gross" style="text-align:right;">{{ number_format($salesVat->gross ?? 0, 2) }}</td><td data-label="VAT" style="text-align:right;">{{ number_format($salesVat->vat_amount ?? 0, 2) }}</td></tr>
<tr style="border-bottom:1px solid var(--color-border);"><td data-label="Source">Invoices</td><td data-label="Count" style="text-align:right;">{{ $invoiceVat->count ?? 0 }}</td><td data-label="Gross" style="text-align:right;">{{ number_format($invoiceVat->gross ?? 0, 2) }}</td><td data-label="VAT" style="text-align:right;">{{ number_format($invoiceVat->vat_amount ?? 0, 2) }}</td></tr>
</tbody>
<tfoot><tr style="font-weight:700;border-top:2px solid var(--color-text);"><td data-label="Total Output VAT" colspan="3">Total Output VAT</td><td data-label="Total" style="text-align:right;">KES {{ number_format($outputVat, 2) }}</td></tr></tfoot>
</table>
</div>
</div>

<div class="card">
<div class="card-body">
<h3 class="text-success" style="margin:0 0 16px;">Input VAT — Tax on Purchases</h3>
<table class="vat-table" style="width:100%;border-collapse:collapse;">
<thead><tr style="border-bottom:2px solid var(--color-border);"><th style="text-align:left;">Source</th><th style="text-align:right;">Count</th><th style="text-align:right;">Gross</th><th style="text-align:right;">VAT</th></tr></thead>
<tbody>
<tr style="border-bottom:1px solid var(--color-border);"><td data-label="Source">Purchase Orders</td><td data-label="Count" style="text-align:right;">{{ $purchaseVat->count ?? 0 }}</td><td data-label="Gross" style="text-align:right;">{{ number_format($purchaseVat->gross ?? 0, 2) }}</td><td data-label="VAT" style="text-align:right;">{{ number_format($purchaseVat->vat_amount ?? 0, 2) }}</td></tr>
</tbody>
<tfoot><tr style="font-weight:700;border-top:2px solid var(--color-text);"><td data-label="Total Input VAT" colspan="3">Total Input VAT</td><td data-label="Total" style="text-align:right;">KES {{ number_format($inputVat, 2) }}</td></tr></tfoot>
</table>
<div class="text-muted" style="margin-top:16px;padding:12px;background:var(--color-surface-2);border-radius:4px;font-size:0.85rem;">
VAT rate used: 16% (standard Kenyan VAT rate). Submit this return via <strong>iTax</strong> by the 20th of the following month.
</div>
</div>
</div>
</div>

</div>{{-- end .page --}}
@endsection
