@extends('layouts.app')
@section('title', 'P9 Certificate - ' . $staffProfile->user->name)
@push('styles')
<style>
@media (max-width: 600px) {
    .p9-header-grid, .p9-relief-grid { grid-template-columns: 1fr !important; }
}
@media (min-width: 769px) {
    .p9-monthly-table th, .p9-monthly-table td { padding: 8px; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <h1>P9 Certificate — {{ $year }}</h1>
    <a href="{{ route('payroll.p9.pdf', [$staffProfile->id, 'year' => $year]) }}" class="btn btn-primary">Download PDF</a>
</div>
<div class="card" style="max-width:900px;">
<div class="card-body">
<div class="p9-header-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
<div>
<strong>Employer</strong><br>{{ $business->name }}<br>
<span class="text-muted" style="font-size:0.85rem;">KRA PIN: {{ $business->kra_pin ?? 'N/A' }}</span>
</div>
<div>
<strong>Employee</strong><br>{{ $staffProfile->user->name }}<br>
<span class="text-muted" style="font-size:0.85rem;">KRA PIN: {{ $staffProfile->kra_pin ?? 'N/A' }}</span><br>
<span class="text-muted" style="font-size:0.85rem;">NSSF: {{ $staffProfile->nssf_no ?? 'N/A' }}</span>
</div>
</div>
<h3 style="margin-bottom:12px;">Monthly Breakdown</h3>
<div class="table-wrapper">
<table class="p9-monthly-table" style="width:100%;border-collapse:collapse;margin-bottom:24px;">
<thead><tr style="border-bottom:2px solid var(--color-text);background:var(--color-surface-2);">
    <th style="text-align:left;">Month</th>
    <th style="text-align:right;">Gross Pay</th>
    <th style="text-align:right;">PAYE</th>
    <th style="text-align:right;">NSSF</th>
    <th style="text-align:right;">SHIF</th>
    <th style="text-align:right;">HELB</th>
    <th style="text-align:right;">Net Pay</th>
</tr></thead>
<tbody>
@foreach($items as $item)
<tr style="border-bottom:1px solid var(--color-border);">
    <td data-label="Month">{{ $item->label ?? \Carbon\Carbon::parse($item->period_start)->format('M Y') }}</td>
    <td data-label="Gross Pay" style="text-align:right;">{{ number_format($item->gross_pay, 2) }}</td>
    <td data-label="PAYE" style="text-align:right;">{{ number_format($item->paye, 2) }}</td>
    <td data-label="NSSF" style="text-align:right;">{{ number_format($item->nssf_employee, 2) }}</td>
    <td data-label="SHIF" style="text-align:right;">{{ number_format($item->shif, 2) }}</td>
    <td data-label="HELB" style="text-align:right;">{{ number_format($item->helb, 2) }}</td>
    <td data-label="Net Pay" style="text-align:right;">{{ number_format($item->net_pay, 2) }}</td>
</tr>
@endforeach
</tbody>
<tfoot>
<tr style="border-top:2px solid var(--color-text);font-weight:700;">
    <td data-label="Month">TOTAL</td>
    <td data-label="Gross Pay" style="text-align:right;">{{ number_format($totals['gross_pay'], 2) }}</td>
    <td data-label="PAYE" style="text-align:right;">{{ number_format($totals['paye'], 2) }}</td>
    <td data-label="NSSF" style="text-align:right;">{{ number_format($totals['nssf_employee'], 2) }}</td>
    <td data-label="SHIF" style="text-align:right;">{{ number_format($totals['shif'], 2) }}</td>
    <td data-label="HELB" style="text-align:right;">{{ number_format($totals['helb'], 2) }}</td>
    <td data-label="Net Pay" style="text-align:right;">{{ number_format($totals['net_pay'], 2) }}</td>
</tr>
</tfoot>
</table>
</div>
<div class="p9-relief-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
<div style="background:var(--color-surface-2);padding:12px;border-radius:4px;">
<strong>Personal Relief Applied:</strong> KSh {{ number_format($totals['personal_relief'], 2) }}
</div>
</div>
</div>
</div>
@endsection
