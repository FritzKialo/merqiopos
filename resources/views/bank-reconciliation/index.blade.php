@extends('layouts.app')
@section('title', 'Bank Reconciliation')

@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Bank Reconciliation</h1>
            <p class="page-subtitle">Match bank statement lines against your sales and expenses.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Add Account --}}
    @role('owner')
    <div class="table-card" style="padding:1.25rem; margin-bottom:1.5rem;">
        <h3 style="margin:0 0 1rem; font-size:0.95rem;">Add Bank Account</h3>
        <form method="POST" action="{{ route('bank-reconciliation.accounts') }}" style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end;">
            @csrf
            <div>
                <label class="form-label">Account Name</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. KCB Current" required>
            </div>
            <div>
                <label class="form-label">Bank</label>
                <input type="text" name="bank_name" class="form-control" placeholder="KCB, Equity...">
            </div>
            <div>
                <label class="form-label">Account Number</label>
                <input type="text" name="account_number" class="form-control" placeholder="1234567890">
            </div>
            <div>
                <label class="form-label">Opening Balance (KSh)</label>
                <input type="number" name="current_balance" class="form-control" step="0.01" placeholder="0.00" style="width:140px;">
            </div>
            <button type="submit" class="btn btn--primary">Add Account</button>
        </form>
    </div>
    @endrole

    {{-- Account Cards --}}
    @if($accounts->isEmpty())
        <div class="empty-state">
            <h3>No bank accounts added yet.</h3>
            <p>Add your first bank account above to get started.</p>
        </div>
    @else
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(340px, 1fr)); gap:1rem;">
            @foreach($accounts as $account)
            <div class="table-card" style="padding:1.25rem;">
                <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:1rem;">
                    <div>
                        <h3 style="margin:0; font-size:1rem;">{{ $account->name }}</h3>
                        <div style="color:var(--color-text-muted); font-size:0.8rem;">
                            {{ $account->bank_name }}{{ $account->account_number ? ' – ' . $account->account_number : '' }}
                        </div>
                    </div>
                    @if($account->unreconciled > 0)
                        <span class="badge badge-warning">{{ $account->unreconciled }} unreconciled</span>
                    @else
                        <span class="badge badge-success">All reconciled</span>
                    @endif
                </div>

                <div style="font-size:1.25rem; font-weight:700; margin-bottom:1rem;">
                    KSh {{ number_format($account->current_balance, 2) }}
                </div>

                <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                    <a href="{{ route('bank-reconciliation.lines', $account) }}" class="btn btn--outline btn--sm">View Lines</a>

                    <form method="POST" action="{{ route('bank-reconciliation.import', $account) }}"
                          enctype="multipart/form-data" style="display:flex; gap:0.5rem; align-items:center;">
                        @csrf
                        <input type="file" name="statement" accept=".csv,.txt" style="font-size:0.8rem; max-width:180px;">
                        <button type="submit" class="btn btn--primary btn--sm">Upload CSV</button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>
@endsection

