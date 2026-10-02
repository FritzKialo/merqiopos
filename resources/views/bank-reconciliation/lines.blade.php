@extends('layouts.app')
@section('title', 'Statement Lines – ' . $account->name)

@push('styles')
<style>
.line-matched { background: #f0fdf4; }
.line-unmatched { background: #fff; }
</style>
@endpush

@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $account->name }} — Statement Lines</h1>
            <p class="page-subtitle">{{ $account->bank_name }}{{ $account->account_number ? ' · ' . $account->account_number : '' }}</p>
        </div>
        <a href="{{ route('bank-reconciliation.index') }}" class="btn btn--outline">Back</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="table-section">
        @if($lines->isEmpty())
            <div class="empty-state">
                <h3>No statement lines imported yet.</h3>
                <p>Upload a CSV bank statement from the accounts list.</p>
            </div>
        @else
            <div class="table-wrapper">
                <table class="table-responsive-cards">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Reference</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Balance</th>
                            <th>Match</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lines as $line)
                        <tr class="{{ $line->is_reconciled ? 'line-matched' : 'line-unmatched' }}">
                            <td data-label="Date">{{ $line->transaction_date->format('d M Y') }}</td>
                            <td data-label="Description" style="font-size:0.85rem; max-width:200px;">{{ $line->description }}</td>
                            <td data-label="Ref" style="color:var(--color-text-muted); font-size:0.8rem;">{{ $line->reference ?? '—' }}</td>
                            <td data-label="Debit" style="color:var(--color-danger);">
                                {{ $line->debit ? 'KSh ' . number_format($line->debit, 2) : '—' }}
                            </td>
                            <td data-label="Credit" style="color:var(--color-success);">
                                {{ $line->credit ? 'KSh ' . number_format($line->credit, 2) : '—' }}
                            </td>
                            <td data-label="Balance">{{ $line->balance ? 'KSh ' . number_format($line->balance, 2) : '—' }}</td>
                            <td data-label="Match" style="font-size:0.8rem; color:var(--color-text-muted);">
                                @if($line->matched_type)
                                    <span class="badge badge-success">{{ ucfirst($line->matched_type) }} #{{ $line->matched_id }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td data-label="Status">
                                @if($line->is_reconciled)
                                    <span class="badge badge-success">Reconciled</span>
                                @else
                                    <span class="badge badge-warning">Unmatched</span>
                                @endif
                            </td>
                            <td data-label="Action">
                                @if(!$line->is_reconciled)
                                <form method="POST" action="{{ route('bank-reconciliation.reconcile', $line) }}"
                                      style="display:flex; gap:4px; align-items:center; flex-wrap:wrap;">
                                    @csrf @method('PATCH')
                                    <select name="matched_type" class="form-control" style="width:90px; font-size:0.75rem; padding:2px 4px;">
                                        <option value="">Type</option>
                                        <option value="sale">Sale</option>
                                        <option value="expense">Expense</option>
                                        <option value="payroll">Payroll</option>
                                    </select>
                                    <input type="number" name="matched_id" placeholder="ID" style="width:60px; font-size:0.75rem; padding:2px 4px; border:1px solid var(--color-border); border-radius:4px;">
                                    <button type="submit" class="btn btn--success btn--sm">Mark</button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $lines->links('vendor.pagination.custom') }}
        @endif
    </div>
</div>
@endsection

