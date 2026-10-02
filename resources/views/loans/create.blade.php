@extends('layouts.app')
@section('title', 'Add Loan')
@push('styles')
<style>
@media (max-width: 640px) {
    .loan-form-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page-header">
    <h1>Add Loan</h1>
    <a href="{{ route('loans.index') }}" class="btn btn-secondary">← Back</a>
</div>

<div class="card" style="max-width:600px;">
    <div class="card-body">
        <form method="POST" action="{{ route('loans.store') }}">
            @csrf
            <div class="loan-form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div style="grid-column:1/-1;">
                    <label class="form-label">Lender Name *</label>
                    <input type="text" name="lender_name" class="form-control" required value="{{ old('lender_name') }}">
                </div>
                <div>
                    <label class="form-label">Loan Type *</label>
                    <select name="loan_type" class="form-control" required>
                        <option value="bank"     {{ old('loan_type','bank')=='bank'     ? 'selected' : '' }}>Bank</option>
                        <option value="sacco"    {{ old('loan_type')=='sacco'    ? 'selected' : '' }}>Sacco</option>
                        <option value="mobile"   {{ old('loan_type')=='mobile'   ? 'selected' : '' }}>Mobile (M-Pesa/Digital)</option>
                        <option value="personal" {{ old('loan_type')=='personal' ? 'selected' : '' }}>Personal</option>
                        <option value="other"    {{ old('loan_type')=='other'    ? 'selected' : '' }}>Other</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Principal Amount (KSh) *</label>
                    <input type="number" name="principal_amount" class="form-control" required step="0.01" min="0" value="{{ old('principal_amount') }}">
                </div>
                <div>
                    <label class="form-label">Interest Rate (%/yr) *</label>
                    <input type="number" name="interest_rate" class="form-control" required step="0.01" min="0" value="{{ old('interest_rate', 0) }}">
                </div>
                <div>
                    <label class="form-label">Disbursement Date *</label>
                    <input type="date" name="disbursement_date" class="form-control" required value="{{ old('disbursement_date', now()->toDateString()) }}">
                </div>
                <div>
                    <label class="form-label">Repayment Start Date *</label>
                    <input type="date" name="repayment_start_date" class="form-control" required value="{{ old('repayment_start_date', now()->toDateString()) }}">
                </div>
                <div>
                    <label class="form-label">Term (months) *</label>
                    <input type="number" name="term_months" class="form-control" required min="1" value="{{ old('term_months') }}">
                    <span class="form-hint">Monthly repayment is calculated automatically from principal, rate, and term.</span>
                </div>
                <div style="grid-column:1/-1;">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                </div>
            </div>
            <div style="margin-top:20px;">
                <button type="submit" class="btn btn-primary">Save Loan</button>
                <a href="{{ route('loans.index') }}" class="btn btn-secondary" style="margin-left:8px;">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
