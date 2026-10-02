@extends('layouts.app')
@section('title', $type . ' Remittance')
@push('styles')
<style>
@media (min-width: 769px) {
    .remittance-table th, .remittance-table td { padding: 10px 12px; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
    <h1>{{ $type }} Remittance Schedule</h1>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;">
            <select name="month" class="form-control" style="width:130px;">
                @for($m=1;$m<=12;$m++)<option value="{{ $m }}" @selected($m==$month)>{{ date('F',mktime(0,0,0,$m,1)) }}</option>@endfor
            </select>
            <select name="year" class="form-control" style="width:100px;">
                @for($y=now()->year;$y>=now()->year-3;$y--)<option value="{{ $y }}" @selected($y==$year)>{{ $y }}</option>@endfor
            </select>
            <button type="submit" class="btn btn-secondary">View</button>
        </form>
        <a href="{{ request()->fullUrlWithQuery(['download'=>'csv']) }}" class="btn btn-primary">Download CSV</a>
    </div>
</div>
<div class="card" style="max-width:900px;">
<div class="card-body">
<h3 style="margin:0 0 4px;">Period: {{ $period }}</h3>
<p class="text-muted" style="font-size:0.85rem;margin:0 0 16px;">{{ $note }}</p>
@if($items->isEmpty())
<p class="text-muted" style="text-align:center;padding:20px 0;">No paid payroll found for this period.</p>
@else
<div class="table-wrapper">
<table class="remittance-table" style="width:100%;border-collapse:collapse;">
<thead><tr style="border-bottom:2px solid var(--color-text);background:var(--color-surface-2);">
@foreach($columns as $col)<th style="text-align:{{ $loop->index===0?'left':'right' }};">{{ $col }}</th>@endforeach
</tr></thead>
<tbody>
@foreach($items as $item)
<tr style="border-bottom:1px solid var(--color-border);">
@foreach($fields as $i => $field)
<td data-label="{{ $columns[$i] ?? '' }}" style="text-align:{{ $i===0?'left':'right' }};">
{{ is_numeric($item->$field ?? null) ? number_format($item->$field, 2) : ($item->$field ?? '—') }}
</td>
@endforeach
</tr>
@endforeach
</tbody>
<tfoot><tr style="border-top:2px solid var(--color-text);font-weight:700;">
<td data-label="{{ $columns[0] ?? '' }}" colspan="{{ count($columns)-1 }}">TOTAL</td>
<td data-label="Total" style="text-align:right;">KSh {{ number_format($total, 2) }}</td>
</tr></tfoot>
</table>
</div>
@endif
</div>
</div>
@endsection
