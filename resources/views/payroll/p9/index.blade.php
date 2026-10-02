@extends('layouts.app')
@section('title', 'P9 Tax Certificates')
@push('styles')
<style>
@media (min-width: 769px) {
    .p9-table th, .p9-table td { padding: 12px 16px; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <h1>P9 Annual Tax Certificates</h1>
    <form method="GET" style="display:flex;gap:8px;align-items:center;">
        <label style="font-weight:500;">Tax Year:</label>
        <select name="year" class="form-control" style="width:120px;" onchange="this.form.submit()">
            @foreach($years as $y)<option value="{{ $y }}" @selected($y==$year)>{{ $y }}</option>@endforeach
        </select>
    </form>
</div>
<div class="card">
<div class="card-body" style="padding:0;">
<div class="table-wrapper">
<table class="p9-table" style="width:100%;border-collapse:collapse;">
<thead><tr style="border-bottom:1px solid #eee;">
    <th style="text-align:left;">Employee</th>
    <th style="text-align:left;">KRA PIN</th>
    <th style="text-align:right;">Actions</th>
</tr></thead>
<tbody>
@forelse($staff as $sp)
<tr style="border-bottom:1px solid #f5f5f5;">
    <td data-label="Employee">{{ $sp->user->name }}</td>
    <td data-label="KRA PIN" style="font-family:monospace;">{{ $sp->kra_pin ?? '—' }}</td>
    <td data-label="Actions" style="text-align:right;">
        <div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;">
        <a href="{{ route('payroll.p9.show', [$sp->id, 'year' => $year]) }}" class="btn btn-secondary" style="padding:4px 10px;font-size:0.8rem;">View P9</a>
        <a href="{{ route('payroll.p9.pdf', [$sp->id, 'year' => $year]) }}" class="btn btn-primary" style="padding:4px 10px;font-size:0.8rem;">Download</a>
        </div>
    </td>
</tr>
@empty
<tr><td colspan="3" style="padding:40px;text-align:center;color:#888;">No paid payroll found for {{ $year }}.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
</div>
@endsection
