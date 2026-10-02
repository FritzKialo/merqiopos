@extends('layouts.app')
@section('title', $supplier->name . ' — Payments')
@push('styles')
<style>
@media (max-width: 900px) {
    .supplier-payments-layout { grid-template-columns: 1fr !important; }
}
@media (min-width: 769px) {
    .supplier-ledger-table th, .supplier-ledger-table td { padding: 0.75rem 1rem; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $supplier->name }}</h1>
            <p class="page-subtitle">Accounts Payable Ledger</p>
        </div>
        <div style="display:flex;gap:0.5rem;">
            <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-secondary">Back to Supplier</a>
        </div>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

    {{-- Balance Summary --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div class="table-card" style="padding:1.25rem;text-align:center;">
            <div style="font-size:0.8rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.5rem;">Outstanding Balance</div>
            <div class="{{ $supplier->payable_balance > 0 ? 'text-danger' : 'text-success' }}" style="font-size:1.75rem;font-weight:700;">
                KSh {{ number_format($supplier->payable_balance, 2) }}
            </div>
        </div>
        <div class="table-card" style="padding:1.25rem;text-align:center;">
            <div style="font-size:0.8rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.5rem;">Total Bills</div>
            <div style="font-size:1.75rem;font-weight:700;">
                KSh {{ number_format($entries->where('type','bill')->sum('amount'), 2) }}
            </div>
        </div>
        <div class="table-card" style="padding:1.25rem;text-align:center;">
            <div style="font-size:0.8rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.5rem;">Total Paid</div>
            <div style="font-size:1.75rem;font-weight:700;color:var(--color-success);">
                KSh {{ number_format($entries->where('type','payment')->sum('amount'), 2) }}
            </div>
        </div>
    </div>

    <div class="supplier-payments-layout" style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;">

        {{-- Ledger Table --}}
        <div>
            <div class="table-card">
                <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--color-border);font-weight:700;">Transaction Ledger</div>
                @if($entries->isEmpty())
                    <div style="padding:2rem;text-align:center;color:var(--color-text-muted);">No transactions yet.</div>
                @else
                <div class="table-wrapper">
                <table class="supplier-ledger-table" style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="background:var(--color-surface);border-bottom:2px solid var(--color-border);">
                            <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Date</th>
                            <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Type</th>
                            <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Reference / Notes</th>
                            <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Amount</th>
                            <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entries as $entry)
                        <tr style="border-bottom:1px solid var(--color-border);">
                            <td data-label="Date" style="font-size:0.9rem;">{{ $entry->payment_date->format('d M Y') }}</td>
                            <td data-label="Type">
                                @if($entry->type === 'bill')
                                    <span class="badge badge-warning">Bill</span>
                                @else
                                    <span class="badge badge-success">Payment</span>
                                @endif
                            </td>
                            <td data-label="Reference / Notes" style="font-size:0.85rem;color:var(--color-text-muted);">
                                @if($entry->reference) <strong>{{ $entry->reference }}</strong><br> @endif
                                {{ $entry->notes }}
                                @if($entry->purchaseOrder)
                                    <br><a href="{{ route('purchases.show', $entry->purchaseOrder) }}" style="font-size:0.8rem;">PO #{{ $entry->purchaseOrder->po_number }}</a>
                                @endif
                            </td>
                            <td data-label="Amount" class="{{ $entry->type === 'bill' ? 'text-danger' : 'text-success' }}" style="text-align:right;font-weight:600;">
                                {{ $entry->type === 'bill' ? '+' : '-' }} KSh {{ number_format($entry->amount, 2) }}
                            </td>
                            <td data-label="Balance" style="text-align:right;font-weight:700;">
                                KSh {{ number_format($entry->balance_after, 2) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
                @endif
            </div>
        </div>

        {{-- Forms --}}
        <div>
            {{-- Record Payment --}}
            @if($supplier->payable_balance > 0)
            <div class="table-card" style="margin-bottom:1rem;padding:1.25rem;">
                <h3 style="font-weight:700;margin-bottom:1rem;font-size:0.95rem;">Record Payment</h3>
                <form method="POST" action="{{ route('suppliers.payments.store', $supplier) }}">
                    @csrf
                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <label class="form-label">Amount (KSh) *</label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="0.01"
                               max="{{ $supplier->payable_balance }}" required
                               value="{{ $supplier->payable_balance }}">
                    </div>
                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <label class="form-label">Payment Method *</label>
                        <select name="payment_method" class="form-control" required>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="cheque">Cheque</option>
                            <option value="cash">Cash</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <label class="form-label">Payment Date *</label>
                        <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-group" style="margin-bottom:1rem;">
                        <label class="form-label">Reference</label>
                        <input type="text" name="reference" class="form-control" placeholder="e.g. Cheque no., M-Pesa ref">
                    </div>
                    <button type="submit" class="btn btn-success" style="width:100%;">Record Payment</button>
                </form>
            </div>
            @endif

            {{-- Add Bill --}}
            <div class="table-card" style="padding:1.25rem;">
                <h3 style="font-weight:700;margin-bottom:1rem;font-size:0.95rem;">Add Bill / Invoice</h3>
                <form method="POST" action="{{ route('suppliers.bill', $supplier) }}">
                    @csrf
                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <label class="form-label">Amount (KSh) *</label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" class="form-control">
                    </div>
                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <label class="form-label">Reference</label>
                        <input type="text" name="reference" class="form-control" placeholder="Invoice / bill number">
                    </div>
                    <div class="form-group" style="margin-bottom:1rem;">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Description of bill"></textarea>
                    </div>
                    <button type="submit" class="btn btn-warning" style="width:100%;">Add Bill</button>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection
