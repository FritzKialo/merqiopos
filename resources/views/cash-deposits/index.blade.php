@extends('layouts.app')
@section('title', 'Cash Deposits')

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Cash Deposits</h1>
        <p class="page-subtitle">Record cash a cashier has handed over, or top up their allowance, for the current shift.</p>
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

@if(!$shiftId)
    <div class="empty-state">
        <h3>No shift is currently open.</h3>
        <p>Deposits and refloats only apply while a shift is open — see Sales &gt; Shifts.</p>
    </div>
@elseif($cashiers->isEmpty())
    <div class="empty-state">
        <h3>No cashiers on this business yet.</h3>
        <p>Digital Float only tracks staff with the cashier role.</p>
    </div>
@else
<div class="table-section" style="margin-bottom:1.5rem;">
    <div class="report-section-header" style="margin-bottom:1rem;">
        <h2>Current Shift — By Cashier</h2>
    </div>
    <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;">
        <thead><tr style="border-bottom:1px solid var(--color-border);background:var(--color-surface-2);">
            <th style="text-align:left;padding:8px 12px;">Cashier</th>
            <th style="text-align:right;padding:8px 12px;">Credit Limit</th>
            <th style="text-align:right;padding:8px 12px;">Cash Sales</th>
            <th style="text-align:right;padding:8px 12px;">Deposits</th>
            <th style="text-align:right;padding:8px 12px;">Refloats</th>
            <th style="text-align:right;padding:8px 12px;">Available Float</th>
            <th style="text-align:right;padding:8px 12px;">Amount Owed</th>
        </tr></thead>
        <tbody>
        @foreach($breakdown as $row)
        <tr style="border-bottom:1px solid var(--color-border);">
            <td data-label="Cashier" style="padding:8px 12px;">{{ $row['user']->name }}</td>
            <td data-label="Credit Limit" style="padding:8px 12px;text-align:right;">KSh {{ number_format($row['credit_limit'], 2) }}</td>
            <td data-label="Cash Sales" style="padding:8px 12px;text-align:right;">KSh {{ number_format($row['cash_sales'], 2) }}</td>
            <td data-label="Deposits" style="padding:8px 12px;text-align:right;color:var(--color-success);">KSh {{ number_format($row['deposits'], 2) }}</td>
            <td data-label="Refloats" style="padding:8px 12px;text-align:right;">KSh {{ number_format($row['refloats'], 2) }}</td>
            <td data-label="Available Float" style="padding:8px 12px;text-align:right;font-weight:600;color:{{ $row['available_float'] <= 0 ? 'var(--color-danger)' : 'var(--color-success)' }};">
                KSh {{ number_format($row['available_float'], 2) }}
                @if($row['available_float'] <= 0)<br><span style="font-size:0.75rem;font-weight:400;">Blocked from cash sales</span>@endif
            </td>
            <td data-label="Amount Owed" style="padding:8px 12px;text-align:right;">KSh {{ number_format($row['amount_owed'], 2) }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>

<div class="table-section">
    <div class="report-section-header" style="margin-bottom:1rem;">
        <h2>Record Deposit or Refloat</h2>
    </div>
    <form method="POST" action="{{ route('cash-deposits.store') }}" style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:flex-end;">
        @csrf
        <div>
            <label class="form-label">Cashier</label>
            <select name="user_id" class="form-control" required style="width:200px;">
                <option value="">Select...</option>
                @foreach($cashiers as $cashier)
                <option value="{{ $cashier->id }}">{{ $cashier->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Type</label>
            <select name="type" class="form-control" style="width:150px;">
                <option value="deposit">Deposit (cash handed over)</option>
                <option value="refloat">Refloat (raise their limit)</option>
            </select>
        </div>
        <div>
            <label class="form-label">Amount (KSh)</label>
            <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required style="width:140px;">
        </div>
        <div>
            <label class="form-label">Notes (optional)</label>
            <input type="text" name="notes" class="form-control" maxlength="500" style="width:220px;">
        </div>
        <button type="submit" class="btn btn--primary">Record</button>
    </form>
</div>
@endif

</div>
@endsection
