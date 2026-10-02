@extends('layouts.app')
@section('title', 'Customer Deposit')
@push('styles')
<style>
@media (max-width: 900px) {
    .cd-show-layout { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h1>Deposit: {{ $customerDeposit->reference }}</h1>
    <div style="display:flex;gap:0.5rem;">
        <a href="{{ route('customer-deposits.index') }}" class="btn btn-secondary">Back</a>
        <form method="POST" action="{{ route('customer-deposits.destroy', $customerDeposit) }}" style="display:inline;"
              onsubmit="return confirm('Delete deposit {{ addslashes($customerDeposit->reference) }}?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger">Delete</button>
        </form>
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if($errors->any())
<div class="alert alert-danger">
    <ul style="margin:0;padding-left:20px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div class="cd-show-layout" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;max-width:1000px;">
<div>
<div class="card">
<div class="card-body">
<h3 style="margin:0 0 16px;">Deposit Details</h3>
<table class="table-plain" style="width:100%;border-collapse:collapse;">
    <tr style="border-bottom:1px solid var(--color-border);"><td class="text-muted" style="padding:8px 0;">Reference</td><td style="padding:8px 0;font-family:monospace;">{{ $customerDeposit->reference }}</td></tr>
    <tr style="border-bottom:1px solid var(--color-border);"><td class="text-muted" style="padding:8px 0;">Customer</td><td style="padding:8px 0;">{{ $customerDeposit->customer?->name ?? '—' }}</td></tr>
    <tr style="border-bottom:1px solid var(--color-border);"><td class="text-muted" style="padding:8px 0;">Total Amount</td><td style="padding:8px 0;font-weight:600;">KSh {{ number_format($customerDeposit->amount, 2) }}</td></tr>
    <tr style="border-bottom:1px solid var(--color-border);"><td class="text-muted" style="padding:8px 0;">Used</td><td style="padding:8px 0;">KSh {{ number_format($customerDeposit->used_amount, 2) }}</td></tr>
    <tr style="border-bottom:1px solid var(--color-border);"><td class="text-muted" style="padding:8px 0;">Available</td><td class="text-success" style="padding:8px 0;font-weight:600;">KSh {{ number_format($customerDeposit->availableBalance(), 2) }}</td></tr>
    <tr style="border-bottom:1px solid var(--color-border);"><td class="text-muted" style="padding:8px 0;">Payment Method</td><td style="padding:8px 0;">{{ ucfirst($customerDeposit->payment_method) }}</td></tr>
    @if($customerDeposit->mpesa_code)<tr style="border-bottom:1px solid var(--color-border);"><td class="text-muted" style="padding:8px 0;">M-Pesa Code</td><td style="padding:8px 0;font-family:monospace;">{{ $customerDeposit->mpesa_code }}</td></tr>@endif
    <tr style="border-bottom:1px solid var(--color-border);"><td class="text-muted" style="padding:8px 0;">Date Received</td><td style="padding:8px 0;">{{ $customerDeposit->received_at?->format('d M Y') }}</td></tr>
    <tr><td class="text-muted" style="padding:8px 0;">Status</td><td style="padding:8px 0;">
        @php $sb=['available'=>'badge-success','partially_used'=>'badge-warning','fully_used'=>'badge-secondary']; @endphp
        <span class="badge {{ $sb[$customerDeposit->status] ?? 'badge-secondary' }}">{{ str_replace('_',' ',ucfirst($customerDeposit->status)) }}</span>
    </td></tr>
</table>
@if($customerDeposit->notes)
<div style="margin-top:12px;padding:10px;background:var(--color-surface-2);border-radius:4px;font-size:0.9rem;"><strong>Notes:</strong> {{ $customerDeposit->notes }}</div>
@endif
</div>
</div>

@if($customerDeposit->status !== 'fully_used')
<div class="card" style="margin-top:16px;">
<div class="card-body">
<h3 style="margin:0 0 16px;">Apply Deposit</h3>
<form method="POST" action="{{ route('customer-deposits.apply', $customerDeposit) }}">
@csrf
<div style="margin-bottom:12px;">
<label class="form-label">Amount to Apply *</label>
<input type="number" name="amount" class="form-control" step="0.01" min="0.01" max="{{ $customerDeposit->availableBalance() }}" required placeholder="Max: {{ number_format($customerDeposit->availableBalance(), 2) }}">
</div>
<div style="margin-bottom:12px;">
<label class="form-label">Invoice ID (optional)</label>
<input type="number" name="invoice_id" class="form-control" placeholder="Leave blank if not linked to invoice">
</div>
<div style="margin-bottom:12px;">
<label class="form-label">Notes</label>
<input type="text" name="notes" class="form-control" placeholder="e.g. Applied to order #123">
</div>
<button type="submit" class="btn btn-primary">Apply Deposit</button>
</form>
</div>
</div>
@endif
</div>

<div>
<div class="card">
<div class="card-body">
<h3 style="margin:0 0 16px;">Usage History</h3>
@forelse($customerDeposit->usages as $usage)
<div style="padding:12px;background:var(--color-surface-2);border-radius:4px;margin-bottom:8px;">
    <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
        <strong>KSh {{ number_format($usage->amount_used, 2) }}</strong>
        <span class="text-muted" style="font-size:0.85rem;">{{ $usage->used_at->format('d M Y H:i') }}</span>
    </div>
    @if($usage->invoice_id)<div class="text-muted" style="font-size:0.85rem;">Invoice #{{ $usage->invoice_id }}</div>@endif
    @if($usage->notes)<div class="text-muted" style="font-size:0.85rem;">{{ $usage->notes }}</div>@endif
</div>
@empty
<p class="text-muted" style="text-align:center;padding:20px;">No usage recorded yet.</p>
@endforelse
</div>
</div>
</div>
</div>
@endsection
