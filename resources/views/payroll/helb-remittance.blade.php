@extends('layouts.app')
@section('title', 'HELB Remittance')
@push('styles')
<style>
@media (min-width: 769px) {
    .helb-table th, .helb-table td { padding: 10px; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
    <h1>HELB Remittance Schedule</h1>
    <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <select name="month" class="form-control" style="width:120px;">
            @for($m=1;$m<=12;$m++)<option value="{{ $m }}" @selected($m==$month)>{{ date('F', mktime(0,0,0,$m,1)) }}</option>@endfor
        </select>
        <select name="year" class="form-control" style="width:100px;">
            @for($y=now()->year;$y>=now()->year-3;$y--)<option value="{{ $y }}" @selected($y==$year)>{{ $y }}</option>@endfor
        </select>
        <button type="submit" class="btn btn-secondary">View</button>
        <a href="{{ request()->fullUrlWithQuery(['download'=>'csv']) }}" class="btn btn-primary">Download CSV</a>
    </form>
</div>
<div class="card" style="max-width:800px;">
<div class="card-body">
<h3 style="margin:0 0 4px;">Period: {{ $period }}</h3>
<p class="text-muted" style="font-size:0.85rem;margin:0 0 16px;">Submit this schedule to HELB with your payment.</p>
@if($items->isEmpty())
<p class="text-muted" style="text-align:center;padding:20px;">No HELB deductions found for this period.</p>
@else
<div class="table-wrapper">
<table class="helb-table" style="width:100%;border-collapse:collapse;">
<thead><tr style="border-bottom:2px solid var(--color-text);">
    <th style="text-align:left;">Employee Name</th>
    <th style="text-align:left;">HELB Account Number</th>
    <th style="text-align:right;">Amount (KSh)</th>
</tr></thead>
<tbody>
@foreach($items as $item)
<tr style="border-bottom:1px solid var(--color-border);">
    <td data-label="Employee Name">{{ $item->name }}</td>
    <td data-label="HELB Account Number" style="font-family:monospace;">{{ $item->helb_account_number ?? 'N/A' }}</td>
    <td data-label="Amount" style="text-align:right;">{{ number_format($item->helb, 2) }}</td>
</tr>
@endforeach
</tbody>
<tfoot>
<tr style="border-top:2px solid var(--color-text);font-weight:700;">
    <td colspan="2" data-label="Total">TOTAL</td>
    <td data-label="Amount" style="text-align:right;">KSh {{ number_format($total, 2) }}</td>
</tr>
</tfoot>
</table>
</div>
@endif
</div>
</div>
@endsection
