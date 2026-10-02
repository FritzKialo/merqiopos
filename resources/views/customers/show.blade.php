@extends('layouts.app')
@section('title', $customer->name)

@push('styles')
    <link rel="stylesheet"
          href="{{ asset('css/customers.css') }}">
    <style>
    @media (max-width: 500px) {
        .customer-credit-summary-grid { grid-template-columns: 1fr !important; }
    }
    </style>
@endpush

@section('content')
<div class="page">

{{-- Profile Header --}}
<div class="profile-header">
    <div class="profile-avatar">
        {{ strtoupper(substr($customer->name, 0, 1)) }}
    </div>
    <div class="profile-info">
        <h2>{{ $customer->name }}</h2>
        <p>
            @if($customer->phone)
                {{ $customer->phone }}
            @endif
            @if($customer->phone && $customer->email)
                &nbsp;&nbsp;
            @endif
            @if($customer->email)
                {{ $customer->email }}
            @endif
        </p>
        @if($customer->address)
            <p>{{ $customer->address }}</p>
        @endif
        <p style="margin-top: 4px;">
            Customer since
            {{ $customer->created_at->format('d M Y') }}
        </p>
    </div>
    <div class="profile-actions">
        @role('owner','manager')
        <a href="{{ route('customers.edit', $customer) }}"
           class="btn btn--primary">
            Edit
        </a>
        <a href="{{ route('customers.statement', $customer) }}"
           class="btn btn--outline">
            Statement
        </a>
        <a href="{{ route('customer.credits.index', $customer) }}"
           class="btn btn--outline">
            Credits
        </a>
        {{-- Working route/controller/mailer existed with zero UI entry point
        anywhere in the app — reachable only by knowing the exact URL and
        POSTing to it directly. Only offer it when the business has the
        portal feature on and the customer has an email to send it to,
        matching CustomerPortalController::invite()'s own validation. --}}
        @if(($customer->business->portal_enabled ?? false) && $customer->email)
        <form method="POST" action="{{ route('customers.portal-invite', $customer) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn--outline">
                {{ $customer->portal_password ? 'Resend Portal Invite' : 'Invite to Portal' }}
            </button>
        </form>
        @endif
        @endrole
        <a href="{{ route('customers.index') }}"
           class="btn btn--outline">
            Back
        </a>
    </div>
</div>

{{-- Debt Banner --}}
@if($customer->hasDebt())
    <div class="debt-banner">
        <div class="debt-banner-text">
            <h3>
                Outstanding Balance:
                KES {{ number_format(
                    $customer->balance_owed, 2) }}
            </h3>
            <p>
                This customer has unpaid invoices.
                Record a payment to clear the balance.
            </p>
        </div>
        @role('owner','manager')
        <button
            class="btn btn--primary"
            onclick="document.getElementById(
                'paymentModal').classList.add('open')">
            Record Payment
        </button>
        @endrole
    </div>
@endif

{{-- Summary KPI Strip --}}
<div class="kpi-strip">
    <div class="kpi-item">
        <span class="kpi-value">KES {{ number_format($summary['total_spent'], 2) }}</span>
        <span class="kpi-label">Total Spent</span>
    </div>
    <div class="kpi-item">
        <span class="kpi-value">{{ $summary['total_purchases'] }}</span>
        <span class="kpi-label">Total Purchases</span>
    </div>
    <div class="kpi-item">
        <span class="kpi-value" style="{{ $summary['balance_owed'] > 0 ? 'color: var(--color-danger);' : 'color: var(--color-success);' }}">
            KES {{ number_format($summary['balance_owed'], 2) }}
        </span>
        <span class="kpi-label">Balance Owed</span>
    </div>
    <div class="kpi-item">
        <span class="kpi-value">
            @if($summary['last_purchase'])
                {{ \Carbon\Carbon::parse($summary['last_purchase'])->format('d M Y') }}
            @else
                —
            @endif
        </span>
        <span class="kpi-label">Last Purchase</span>
    </div>
    @if($customer->loyalty_points > 0)
    <div class="kpi-item">
        <span class="kpi-value" style="color:var(--color-success);">{{ number_format($customer->loyalty_points, 0) }}</span>
        <span class="kpi-label">
            Loyalty Points
            <a href="{{ route('customers.loyalty', $customer) }}" style="font-size:0.75rem; color:var(--color-text-muted); margin-left:4px;">View</a>
        </span>
    </div>
    @endif
</div>

{{-- Notes --}}
@if($customer->notes)
    <div style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--color-border);">
        <strong style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--color-text-muted);">Notes</strong>
        <p style="margin-top: 6px; color: var(--color-text); font-size: 0.9rem;">{{ $customer->notes }}</p>
    </div>
@endif

{{-- Credit Limit --}}
@role('owner','manager')
<div class="card" style="margin-bottom:1.5rem;">
<div class="card-body" style="padding:1.25rem;">
<h3 style="margin:0 0 12px;font-size:1rem;">Credit Limit</h3>
<form method="POST" action="{{ route('customers.credit-limit', $customer) }}">
@csrf
<div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
        <input type="checkbox" name="credit_limit_enabled" value="1" {{ $customer->credit_limit_enabled ? 'checked' : '' }}> Enable Credit Limit
    </label>
    <div style="display:flex;align-items:center;gap:8px;">
        <label class="form-label" style="margin:0;">Limit (KES):</label>
        <input type="number" name="credit_limit" class="form-control" style="width:160px;" value="{{ $customer->credit_limit ?? 0 }}" step="0.01" min="0">
    </div>
    <button type="submit" class="btn btn--primary">Update</button>
</div>
@if($customer->credit_limit_enabled && $customer->credit_limit > 0)
@php
    // Was 'total_amount' — invoices' real column is 'total' (confirmed
    // via Schema::getColumnListing). Since credit_limit_enabled never
    // actually existed as a real DB column until this same session's
    // fix, this block was completely unreachable and the wrong column
    // name here had never actually been hit — would have thrown
    // "Unknown column" the first time a real customer had
    // credit_limit_enabled=true.
    $outstanding = \App\Models\Invoice::where('customer_id',$customer->id)->whereIn('status',['sent','partially_paid'])->sum('total') ?? 0;
    $available = max(0, $customer->credit_limit - $outstanding);
@endphp
<div class="customer-credit-summary-grid" style="margin-top:12px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
    <div style="text-align:center;padding:8px;background:var(--color-surface-2);border-radius:4px;"><div class="text-muted" style="font-size:0.78rem;">Credit Limit</div><div style="font-weight:700;">KES {{ number_format($customer->credit_limit,2) }}</div></div>
    <div style="text-align:center;padding:8px;background:var(--color-surface-2);border-radius:4px;"><div class="text-muted" style="font-size:0.78rem;">Outstanding</div><div class="text-danger" style="font-weight:700;">KES {{ number_format($outstanding,2) }}</div></div>
    <div style="text-align:center;padding:8px;background:var(--color-surface-2);border-radius:4px;"><div class="text-muted" style="font-size:0.78rem;">Available Credit</div><div class="text-success" style="font-weight:700;">KES {{ number_format($available,2) }}</div></div>
</div>
@endif
</form>
</div>
</div>
@endrole

{{-- Purchase History --}}
<div class="report-section">
    <div class="report-section-header">
        <h2>Purchase History</h2>
    </div>

    @if($sales->isEmpty())
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <h3>No purchases yet</h3>
            <p>
                This customer has not made
                any purchases yet.
            </p>
        </div>
    @else
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sales as $sale)
                    <tr>
                        <td data-label="Invoice">
                            <strong style="
                                color: var(--color-primary);">
                                {{ $sale->invoice_number }}
                            </strong>
                        </td>
                        <td data-label="Items">
                            {{ $sale->items->count() }}
                        </td>
                        <td data-label="Total">
                            <strong>
                                KES {{ number_format(
                                    $sale->total_amount,
                                    2) }}
                            </strong>
                        </td>
                        <td data-label="Paid">
                            KES {{ number_format(
                                $sale->paid_amount, 2) }}
                        </td>
                        <td data-label="Balance">
                            @if($sale->balance_due > 0)
                                <span style="
                                    color:       var(--color-danger);
                                    font-weight: 600;">
                                    KES {{ number_format(
                                        $sale->balance_due,
                                        2) }}
                                </span>
                            @else
                                <span style="
                                    color: var(--color-success);">
                                    —
                                </span>
                            @endif
                        </td>
                        <td data-label="Status">
                            @if($sale->payment_status === 'paid')
                                <span class="badge badge-success">
                                    Paid
                                </span>
                            @elseif($sale->payment_status === 'partial')
                                <span class="badge badge-warning">
                                    Partial
                                </span>
                            @else
                                <span class="badge badge-danger">
                                    Unpaid
                                </span>
                            @endif
                        </td>
                        <td data-label="Date" style="white-space: nowrap;">
                            {{ $sale->created_at
                                ->format('d M Y') }}
                        </td>
                        <td data-label="">
                            <a href="{{ route(
                                'sales.show', $sale) }}"
                               class="btn btn--outline btn--sm">
                                View
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $sales->links('vendor.pagination.custom') }}
    @endif
</div>{{-- end .report-section --}}

{{-- Payment Modal --}}
@if($customer->hasDebt())
<div class="modal-overlay" id="paymentModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Record Payment</h3>
            <button class="modal-close"
                    onclick="document.getElementById(
                        'paymentModal')
                        .classList.remove('open')">
                &times;
            </button>
        </div>

        <p style="
            font-size:     0.875rem;
            color:         var(--color-text-muted);
            margin-bottom: var(--space-4);">
            Outstanding balance:
            <strong style="color: var(--color-danger);">
                KES {{ number_format(
                    $customer->balance_owed, 2) }}
            </strong>
        </p>

        <form method="POST"
              action="{{ route(
                'customers.payment', $customer) }}">
            @csrf

            <div class="form-group">
                <label class="form-label">
                    Amount Received (KES) *
                </label>
                <input
                    type="number"
                    name="payment_amount"
                    class="form-control
                        {{ $errors->has('payment_amount')
                            ? 'is-invalid' : '' }}"
                    value="{{ old('payment_amount',
                        $customer->balance_owed) }}"
                    min="1"
                    max="{{ $customer->balance_owed }}"
                    step="0.01"
                    required>
                @error('payment_amount')
                    <span class="invalid-feedback">
                        {{ $message }}
                    </span>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label">
                    Payment Method *
                </label>
                <select name="payment_method"
                        class="form-control"
                        id="modalPaymentMethod"
                        onchange="
                            var ref = document.getElementById(
                                'modalMpesaRef');
                            ref.style.display =
                                this.value === 'mpesa'
                                    ? 'block' : 'none';">
                    <option value="cash">Cash</option>
                    <option value="mpesa">M-Pesa</option>
                    <option value="bank_transfer">
                        Bank Transfer
                    </option>
                </select>
            </div>

            <div class="form-group"
                 id="modalMpesaRef"
                 style="display: none;">
                <label class="form-label">
                    M-Pesa Reference
                </label>
                <input
                    type="text"
                    name="mpesa_reference"
                    class="form-control"
                    placeholder="e.g. QGH7JK2XYZ">
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn--outline"
                    onclick="document.getElementById(
                        'paymentModal')
                        .classList.remove('open')">
                    Cancel
                </button>
                <button type="submit"
                        class="btn btn--primary">
                    Confirm Payment
                </button>
            </div>
        </form>
    </div>
</div>
@endif

</div>{{-- end .page --}}
@endsection

@push('scripts')
    <script src="{{ asset('js/customers.js') }}"></script>
@endpush
