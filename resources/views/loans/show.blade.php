@extends('layouts.app')
@section('title', 'Loan — ' . $loan->lender_name)
@push('styles')
<style>
@media (max-width: 800px) {
    .loan-show-layout { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page-header">
    <h1>{{ $loan->lender_name }}</h1>
    <div style="display:flex;gap:0.5rem;">
        <a href="{{ route('loans.index') }}" class="btn btn-secondary">← Back</a>
        <form method="POST" action="{{ route('loans.destroy', $loan) }}" style="display:inline;"
              onsubmit="return confirm('Delete this loan from {{ addslashes($loan->lender_name) }}? This removes its repayment history.')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger">Delete</button>
        </form>
    </div>
</div>

<div class="loan-show-layout" style="display:grid; grid-template-columns:1fr 1fr; gap:24px; max-width:800px;">
    <div class="card">
        <div class="card-body">
            <h3 style="margin:0 0 16px;">Loan Details</h3>
            <table class="table-plain" style="width:100%; font-size:0.9rem;">
                <tr><td class="text-muted" style="padding:6px 0;">Principal</td><td style="font-weight:bold; text-align:right;">KSh {{ number_format($loan->principal, 2) }}</td></tr>
                <tr><td class="text-muted" style="padding:6px 0;">Interest Rate</td><td style="text-align:right;">{{ $loan->interest_rate }}%/yr</td></tr>
                <tr><td class="text-muted" style="padding:6px 0;">Outstanding Balance</td><td style="font-weight:bold; text-align:right;">KSh {{ number_format($loan->balance, 2) }}</td></tr>
                <tr><td class="text-muted" style="padding:6px 0;">Monthly Repayment</td><td style="text-align:right;">KSh {{ number_format($loan->monthly_installment, 0) }}</td></tr>
                <tr><td class="text-muted" style="padding:6px 0;">Disbursed</td><td style="text-align:right;">{{ $loan->disbursement_date->format('d M Y') }}</td></tr>
                <tr><td class="text-muted" style="padding:6px 0;">Due Date</td><td style="text-align:right;">{{ $loan->due_date ? $loan->due_date->format('d M Y') : '—' }}</td></tr>
                <tr><td class="text-muted" style="padding:6px 0;">Status</td><td style="text-align:right;">
                    @if($loan->isOverdue())<span class="badge badge-danger">Overdue</span>
                    @elseif($loan->status === 'active')<span class="badge badge-success">Active</span>
                    @else<span class="badge badge-secondary">{{ ucfirst(str_replace('_',' ',$loan->status)) }}</span>@endif
                </td></tr>
            </table>
            @if($loan->notes)
            <div class="text-muted" style="margin-top:12px; padding:10px; background:var(--color-surface-2); border-radius:6px; font-size:0.85rem;">{{ $loan->notes }}</div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="margin:0;">Record Repayment</h3>
            </div>
            @if($errors->any())
                <div class="alert alert-danger" style="margin-bottom:12px;">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('loans.repayment', $loan) }}">
                @csrf
                <div style="margin-bottom:12px;">
                    <label class="form-label">Amount (KSh) *</label>
                    <input type="number" name="amount" class="form-control" required step="0.01" min="1" max="{{ $loan->outstanding_balance }}" placeholder="{{ number_format($loan->monthly_installment ?? 0, 0) }}">
                </div>
                <div style="margin-bottom:12px;">
                    <label class="form-label">Date *</label>
                    <input type="date" name="payment_date" class="form-control" required value="{{ old('payment_date', now()->toDateString()) }}">
                </div>
                <div style="margin-bottom:12px;">
                    <label class="form-label">Method *</label>
                    <select name="payment_method" class="form-control" required>
                        <option value="cash">Cash</option>
                        <option value="mpesa">M-Pesa</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>
                <div style="margin-bottom:16px;">
                    <label class="form-label">Reference</label>
                    <input type="text" name="reference" class="form-control" maxlength="100">
                </div>
                <button type="submit" class="btn btn-primary">Record Payment</button>
            </form>
        </div>
    </div>
</div>

@if($repayments->isNotEmpty())
<div class="card" style="max-width:800px; margin-top:1rem;">
    <div class="card-body">
        <h3 style="margin:0 0 12px;">Repayment History</h3>
        <table class="table-plain" style="width:100%;">
            <thead><tr><th>Date</th><th>Amount</th><th>Interest</th><th>Principal</th><th>Balance after</th></tr></thead>
            <tbody>
            @foreach($repayments as $rp)
                <tr>
                    <td>{{ $rp->payment_date->format('d M Y') }}</td>
                    <td>KSh {{ number_format($rp->amount, 2) }}</td>
                    <td>KSh {{ number_format($rp->interest_portion, 2) }}</td>
                    <td>KSh {{ number_format($rp->principal_portion, 2) }}</td>
                    <td>KSh {{ number_format($rp->balance_after, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="card" style="max-width:800px; margin-top:1rem;">
    <div class="card-body">
        @include('partials.attachments', ['modelType' => 'Loan', 'modelId' => $loan->id])
    </div>
</div>
@endsection
