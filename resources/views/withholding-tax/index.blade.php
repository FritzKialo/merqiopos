@extends('layouts.app')
@section('title', 'Withholding Tax')
@push('styles')
<style>
@media (min-width: 769px) {
    .wht-table th { padding: 12px 16px; }
    .wht-table td { padding: 10px 16px; }
}
@media (max-width: 700px) {
    .wht-stats-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <h1>Withholding Tax (WHT)</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('withholding-tax.export', request()->query()) }}" class="btn btn-secondary">Export CSV</a>
        <a href="{{ route('withholding-tax.create') }}" class="btn btn-primary">+ Record WHT</a>
    </div>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="wht-stats-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:24px;">
    <div class="stat-card"><div class="stat-label">Total WHT This Year</div><div class="stat-value">KSh {{ number_format($totalWht, 2) }}</div></div>
    <div class="stat-card"><div class="stat-label">Records</div><div class="stat-value">{{ $records->total() }}</div></div>
    <div class="stat-card"><div class="stat-label">Filing Deadline</div><div class="stat-value" style="font-size:1rem;">20th of following month</div></div>
</div>
<div class="card"><div class="card-body" style="padding:0;">
<table class="wht-table" style="width:100%;border-collapse:collapse;">
<thead><tr style="border-bottom:1px solid #eee;">
    <th style="text-align:left;">Payee</th>
    <th style="text-align:left;">KRA PIN</th>
    <th style="text-align:left;">Type</th>
    <th style="text-align:right;">Gross</th>
    <th style="text-align:right;">Rate</th>
    <th style="text-align:right;">WHT</th>
    <th style="text-align:right;">Net Paid</th>
    <th style="text-align:left;">Date</th>
    <th></th>
</tr></thead>
<tbody>
@forelse($records as $r)
<tr style="border-bottom:1px solid #f5f5f5;">
    <td data-label="Payee">{{ $r->payee_name }}</td>
    <td data-label="KRA PIN" style="font-family:monospace;font-size:0.85rem;">{{ $r->payee_kra_pin ?? '—' }}</td>
    <td data-label="Type" style="font-size:0.85rem;">{{ ucfirst(str_replace('_',' ',$r->wht_type)) }}</td>
    <td data-label="Gross" style="text-align:right;">{{ number_format($r->gross_amount, 2) }}</td>
    <td data-label="Rate" style="text-align:right;">{{ $r->wht_rate }}%</td>
    <td data-label="WHT" style="text-align:right;font-weight:600;color:#dc3545;">{{ number_format($r->wht_amount, 2) }}</td>
    <td data-label="Net Paid" style="text-align:right;">{{ number_format($r->net_amount, 2) }}</td>
    <td data-label="Date">{{ $r->payment_date->format('d M Y') }}</td>
    <td data-label="">
        <form method="POST" action="{{ route('withholding-tax.destroy', $r) }}" style="display:inline;" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')
            <button class="btn btn-danger" style="padding:3px 8px;font-size:0.78rem;">Del</button>
        </form>
    </td>
</tr>
@empty
<tr><td colspan="9" style="padding:40px;text-align:center;color:#888;">No WHT records yet.</td></tr>
@endforelse
</tbody>
</table>
</div></div>
{{ $records->links() }}
@endsection
