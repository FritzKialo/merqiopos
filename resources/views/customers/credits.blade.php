@extends('layouts.app')
@section('title', $customer->name . ' — Credits')
@push('styles')
<style>
@media (max-width: 700px) {
    .customer-credits-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $customer->name }}</h1>
            <p class="page-subtitle">Store credit ledger — credit you owe this customer, from returns and credit notes. It can be used to pay for a sale.</p>
        </div>
        <a href="{{ route('customers.show', $customer) }}" class="btn btn--outline">Back to Customer</a>
    </div>

    @if(session('success')) <div class="alert alert--success">{{ session('success') }}</div> @endif
    @if(session('error'))   <div class="alert alert--error">{{ session('error') }}</div> @endif
    @if($errors->any())
        <div class="alert alert--error">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
    @endif

    {{-- Credit Summary --}}
    <div class="customer-credits-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">
        <div class="card">
            <div class="card-body" style="text-align:center;">
                <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);">Credit Limit</div>
                <div style="font-size:28px;font-weight:700;margin:0.5rem 0;">KES {{ number_format($customer->credit_limit, 2) }}</div>
                <form method="POST" action="{{ route('customer.credits.limit', $customer) }}" style="display:flex;gap:0.5rem;">
                    @csrf @method('PATCH')
                    <input type="number" name="credit_limit" class="form-control" min="0" step="1"
                           value="{{ $customer->credit_limit }}" style="flex:1;">
                    <button class="btn btn--outline btn--sm">Update</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-body" style="text-align:center;">
                <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);">Store Credit Balance</div>
                <div style="font-size:28px;font-weight:700;margin:0.5rem 0;color:{{ $customer->credit_balance > 0 ? 'var(--color-success)' : 'var(--text-muted)' }};">
                    KES {{ number_format($customer->credit_balance, 2) }}
                </div>
                
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <h3 style="margin-bottom:1rem;">Use Store Credit</h3>
                @if($customer->credit_balance > 0)
                <form method="POST" action="{{ route('customer.credits.store', $customer) }}">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">Amount (KES) *</label>
                        <input type="number" name="amount" class="form-control" min="0.01"
                               max="{{ $customer->credit_balance }}" step="0.01"
                               value="{{ old('amount', $customer->credit_balance) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reference</label>
                        <input type="text" name="reference" class="form-control" placeholder="e.g. Receipt #">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn--success" style="width:100%;">Deduct Store Credit</button>
                </form>
                @else
                <p style="color:var(--color-success);">This customer has no store credit.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Ledger --}}
    <div class="card">
        <div class="card-body"><h3>Credit Ledger</h3></div>
        <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr><th>Date</th><th>Type</th><th>Amount</th><th>Balance After</th><th>Reference</th><th>By</th></tr>
            </thead>
            <tbody>
                @forelse($credits as $credit)
                <tr>
                    <td data-label="Date">{{ $credit->created_at->format('d M Y H:i') }}</td>
                    <td data-label="Type">
                        @php $typeColors = ['credit_sale'=>'badge-danger','repayment'=>'badge-success','store_credit'=>'badge-success','adjustment'=>'badge-secondary']; @endphp
                        <span class="badge {{ $typeColors[$credit->type] ?? 'badge-secondary' }}">{{ ucfirst(str_replace('_',' ',$credit->type)) }}</span>
                    </td>
                    <td data-label="Amount">KES {{ number_format($credit->amount, 2) }}</td>
                    <td data-label="Balance After">KES {{ number_format($credit->balance_after, 2) }}</td>
                    <td data-label="Reference">{{ $credit->reference ?? '—' }}</td>
                    <td data-label="By">{{ $credit->user?->name }}</td>
                </tr>
                @empty
                <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:2rem;">No credit transactions.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    {{ $credits->links() }}
</div>
@endsection

