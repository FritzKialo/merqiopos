@extends('layouts.app')
@section('title', 'Petty Cash')
@push('styles')
<style>
@media (max-width: 900px) {
    .petty-cash-layout { grid-template-columns: 1fr !important; }
}
@media (min-width: 769px) {
    .petty-cash-table th, .petty-cash-table td { padding: 0.75rem 1rem; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Petty Cash</h1>
            <p class="page-subtitle">{{ $account->name }} — Float Management</p>
        </div>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

    {{-- Balance Card --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div class="table-card" style="padding:1.5rem;text-align:center;">
            <div style="font-size:0.8rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.5rem;">Current Balance</div>
            <div class="{{ $account->current_balance <= 0 ? 'text-danger' : 'text-success' }}" style="font-size:2rem;font-weight:700;">
                KSh {{ number_format($account->current_balance, 2) }}
            </div>
        </div>
        <div class="table-card" style="padding:1.5rem;text-align:center;">
            <div style="font-size:0.8rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.5rem;">Total Top-ups</div>
            <div style="font-size:1.5rem;font-weight:700;">
                KSh {{ number_format($transactions->getCollection()->where('type','topup')->sum('amount') + ($transactions->currentPage() > 1 ? 0 : 0), 2) }}
            </div>
        </div>
        <div class="table-card" style="padding:1.5rem;text-align:center;">
            <div style="font-size:0.8rem;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.5rem;">Disbursements (this page)</div>
            <div style="font-size:1.5rem;font-weight:700;color:var(--color-danger);">
                KSh {{ number_format($transactions->getCollection()->where('type','disbursement')->sum('amount'), 2) }}
            </div>
        </div>
    </div>

    <div class="petty-cash-layout" style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;">

        {{-- Transactions Table --}}
        <div>
            <div class="table-card">
                <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--color-border);font-weight:700;">Transactions</div>
                @if($transactions->isEmpty())
                    <div style="padding:2rem;text-align:center;color:var(--color-text-muted);">No transactions yet. Top up the petty cash float to get started.</div>
                @else
                <table class="petty-cash-table" style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="background:var(--color-surface);border-bottom:2px solid var(--color-border);">
                            <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Date</th>
                            <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Description</th>
                            <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Category</th>
                            <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Type</th>
                            <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Amount</th>
                            <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $txn)
                        <tr style="border-bottom:1px solid var(--color-border);">
                            <td data-label="Date" style="font-size:0.9rem;">{{ $txn->transaction_date->format('d M Y') }}</td>
                            <td data-label="Description">
                                <div style="font-weight:600;font-size:0.9rem;">{{ $txn->description }}</div>
                                @if($txn->receipt_number)
                                    <div style="font-size:0.78rem;color:var(--color-text-muted);">Receipt: {{ $txn->receipt_number }}</div>
                                @endif
                                @if($txn->user)
                                    <div style="font-size:0.78rem;color:var(--color-text-muted);">By: {{ $txn->user->name }}</div>
                                @endif
                            </td>
                            <td data-label="Category" style="font-size:0.85rem;color:var(--color-text-muted);">{{ $txn->category ?? '—' }}</td>
                            <td data-label="Type">
                                @if($txn->type === 'topup')
                                    <span class="badge badge-success">Top-up</span>
                                @else
                                    <span class="badge badge-danger">Disbursement</span>
                                @endif
                            </td>
                            <td data-label="Amount" class="{{ $txn->type === 'topup' ? 'text-success' : 'text-danger' }}" style="text-align:right;font-weight:600;">
                                {{ $txn->type === 'topup' ? '+' : '-' }} KSh {{ number_format($txn->amount, 2) }}
                            </td>
                            <td data-label="Balance" style="text-align:right;font-weight:700;">
                                KSh {{ number_format($txn->balance_after, 2) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($transactions->hasPages())
                <div style="padding:1rem 1.25rem;">{{ $transactions->links() }}</div>
                @endif
                @endif
            </div>
        </div>

        {{-- Forms --}}
        <div>
            {{-- Top Up Form --}}
            <div class="table-card" style="margin-bottom:1rem;padding:1.25rem;">
                <h3 style="font-weight:700;margin-bottom:1rem;font-size:0.95rem;">Top Up Float</h3>
                <form method="POST" action="{{ route('petty-cash.topup') }}">
                    @csrf
                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <label class="form-label">Amount (KSh) *</label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <label class="form-label">Description *</label>
                        <input type="text" name="description" class="form-control" placeholder="e.g. Monthly top-up" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <label class="form-label">Reference</label>
                        <input type="text" name="reference" class="form-control" placeholder="e.g. Journal voucher no.">
                    </div>
                    <div class="form-group" style="margin-bottom:1rem;">
                        <label class="form-label">Date *</label>
                        <input type="date" name="transaction_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <button type="submit" class="btn btn-success" style="width:100%;">Add Top-up</button>
                </form>
            </div>

            {{-- Disburse Form --}}
            <div class="table-card" style="padding:1.25rem;">
                <h3 style="font-weight:700;margin-bottom:1rem;font-size:0.95rem;">Record Disbursement</h3>
                @if($account->current_balance <= 0)
                    <div class="alert alert-warning" style="font-size:0.85rem;">Top up the float first before recording disbursements.</div>
                @else
                <form method="POST" action="{{ route('petty-cash.disburse') }}">
                    @csrf
                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <label class="form-label">Amount (KSh) *</label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="0.01"
                               max="{{ $account->current_balance }}" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <label class="form-label">Description *</label>
                        <input type="text" name="description" class="form-control" placeholder="What was the money spent on?" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <label class="form-label">Category</label>
                        <input type="text" name="category" class="form-control"
                               list="category-list"
                               placeholder="e.g. Office Supplies, Transport">
                        <datalist id="category-list">
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}">
                            @endforeach
                            <option value="Office Supplies">
                            <option value="Transport">
                            <option value="Utilities">
                            <option value="Refreshments">
                            <option value="Postage">
                            <option value="Cleaning">
                        </datalist>
                    </div>
                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <label class="form-label">Receipt Number</label>
                        <input type="text" name="receipt_number" class="form-control">
                    </div>
                    <div class="form-group" style="margin-bottom:1rem;">
                        <label class="form-label">Date *</label>
                        <input type="date" name="transaction_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <button type="submit" class="btn btn-danger" style="width:100%;">Record Disbursement</button>
                </form>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
