@extends('layouts.app')
@section('title', 'Commission Earnings')
@push('styles')
<style>
@media (min-width: 769px) {
    .commission-earnings-table th, .commission-earnings-table td { padding: 12px 16px; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h1>Commission Earnings</h1>
    <a href="{{ route('staff.commissions.index') }}" class="btn btn-secondary">Manage Rules</a>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

{{-- Calculate form --}}
<div class="card" style="margin-bottom:20px;">
<div class="card-body">
<form method="POST" action="{{ route('staff.commissions.calculate') }}" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
@csrf
<div>
    <label style="display:block;font-size:0.85rem;font-weight:500;margin-bottom:4px;">Month</label>
    <select name="month" class="form-control" style="width:130px;">
        @for($m=1;$m<=12;$m++)
        <option value="{{ $m }}" @selected($m==$month)>{{ date('F', mktime(0,0,0,$m,1)) }}</option>
        @endfor
    </select>
</div>
<div>
    <label style="display:block;font-size:0.85rem;font-weight:500;margin-bottom:4px;">Year</label>
    <select name="year" class="form-control" style="width:100px;">
        @for($y=now()->year;$y>=now()->year-2;$y--)
        <option value="{{ $y }}" @selected($y==$year)>{{ $y }}</option>
        @endfor
    </select>
</div>
<button type="submit" class="btn btn-primary">Calculate Commissions</button>
</form>
</div>
</div>

{{-- Filter / View --}}
<form method="GET" style="display:flex;gap:8px;align-items:flex-end;margin-bottom:16px;flex-wrap:wrap;">
    <div>
        <label style="font-size:0.85rem;font-weight:500;">Month</label>
        <select name="month" class="form-control" style="width:130px;">
            @for($m=1;$m<=12;$m++)
            <option value="{{ $m }}" @selected($m==$month)>{{ date('F', mktime(0,0,0,$m,1)) }}</option>
            @endfor
        </select>
    </div>
    <div>
        <label style="font-size:0.85rem;font-weight:500;">Year</label>
        <select name="year" class="form-control" style="width:100px;">
            @for($y=now()->year;$y>=now()->year-2;$y--)
            <option value="{{ $y }}" @selected($y==$year)>{{ $y }}</option>
            @endfor
        </select>
    </div>
    <div>
        <label style="font-size:0.85rem;font-weight:500;">Staff</label>
        <select name="staff_profile_id" class="form-control" style="width:180px;">
            <option value="">All Staff</option>
            @foreach($staffProfiles as $sp)
            <option value="{{ $sp->id }}" @selected($staffId == $sp->id)>{{ $sp->user->name }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="btn btn-secondary">Filter</button>
</form>

<div class="card">
<div class="card-body" style="padding:0;">
<div class="table-wrapper">
<table class="commission-earnings-table" style="width:100%;border-collapse:collapse;">
<thead><tr style="border-bottom:1px solid var(--color-border);background:var(--color-surface-2);">
    <th style="text-align:left;">Employee</th>
    <th style="text-align:right;">Gross Sales</th>
    <th style="text-align:right;">Commission</th>
    <th style="text-align:center;">Status</th>
    <th style="text-align:right;">Actions</th>
</tr></thead>
<tbody>
@forelse($earnings as $e)
<tr style="border-bottom:1px solid var(--color-border);">
    <td data-label="Employee">{{ $e->staffProfile?->user?->name ?? '—' }}</td>
    <td data-label="Gross Sales" style="text-align:right;">KSh {{ number_format($e->gross_sales, 2) }}</td>
    <td data-label="Commission" style="text-align:right;font-weight:600;">KSh {{ number_format($e->commission_amount, 2) }}</td>
    <td data-label="Status" style="text-align:center;">
        @php
            $badges = ['pending'=>'badge-warning','approved'=>'badge-success','paid'=>'badge-blue'];
            $badgeClass = $badges[$e->status] ?? 'badge-secondary';
        @endphp
        <span class="badge {{ $badgeClass }}" style="font-size:0.75rem;">
            {{ ucfirst($e->status) }}
        </span>
    </td>
    <td data-label="Actions" style="text-align:right;">
        @if($e->status === 'pending')
        <form method="POST" action="{{ route('staff.commissions.approve', $e) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-primary" style="padding:4px 10px;font-size:0.8rem;">Approve</button>
        </form>
        @else
        <span class="text-muted" style="font-size:0.85rem;">—</span>
        @endif
    </td>
</tr>
@empty
<tr><td colspan="5" class="text-muted" style="padding:40px;text-align:center;">No earnings found. Use the Calculate button above.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
</div>
{{ $earnings->links() }}
@endsection
