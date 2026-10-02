@extends('layouts.app')
@section('title', $invoice->invoice_number)
@push('styles')
<style>
@media (max-width: 900px) {
    .invoice-show-layout { grid-template-columns: 1fr !important; }
}
@media (max-width: 500px) {
    .invoice-detail-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $invoice->invoice_number }}</h1>
            @php
                $badge = match($invoice->status) { 'paid' => 'badge--green', 'draft' => 'badge--gray', 'partial' => 'badge--gray', 'overdue' => 'badge--red', default => 'badge--gray' };
            @endphp
            <p class="page-subtitle"><span class="badge {{ $badge }}">{{ ucfirst($invoice->status) }}</span></p>
        </div>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            @if($invoice->status === 'draft')
                <form method="POST" action="{{ route('invoices.send', $invoice) }}" style="display:inline;">
                    @csrf @method('PATCH')
                    <button class="btn btn--success">Mark as Sent</button>
                </form>
                <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn--outline">Edit</a>
                <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" style="display:inline;"
                      onsubmit="return confirm('Delete this invoice?')">
                    @csrf @method('DELETE')
                    <button class="btn btn--danger">Delete</button>
                </form>
            @endif
            <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn--outline" target="_blank">Print / PDF</a>
            <a href="{{ route('invoices.index') }}" class="btn btn--outline">Back</a>
        </div>
    </div>

    @if(session('success')) <div class="alert alert--success">{{ session('success') }}</div> @endif
    @if(session('error'))   <div class="alert alert--error">{{ session('error') }}</div> @endif

    <div class="invoice-show-layout" style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;">
        <div>
            {{-- Invoice details --}}
            <div class="table-card" style="margin-bottom:1.5rem;">
                <div class="card-body">
                    <div class="invoice-detail-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                        <div><small style="color:var(--text-muted);">Customer</small><br><strong>{{ $invoice->customer?->name ?? 'No customer' }}</strong></div>
                        <div><small style="color:var(--text-muted);">Issue Date</small><br>{{ $invoice->issue_date->format('d M Y') }}</div>
                        <div><small style="color:var(--text-muted);">Due Date</small><br>{{ $invoice->due_date->format('d M Y') }}</div>
                    </div>
                    @if($invoice->notes)
                    <div style="margin-top:0.75rem;"><small style="color:var(--text-muted);">Notes:</small><br>{{ $invoice->notes }}</div>
                    @endif
                </div>
                <table class="table">
                    <thead>
                        <tr><th>Description</th><th>Qty</th><th>Unit Price</th><th>VAT %</th><th>VAT</th><th>Subtotal</th></tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->items as $item)
                        <tr>
                            <td data-label="Description">{{ $item->description }}</td>
                            <td data-label="Qty">{{ $item->quantity }}</td>
                            <td data-label="Unit Price">KSh {{ number_format($item->unit_price, 0) }}</td>
                            <td data-label="VAT %">{{ $item->vat_rate }}%</td>
                            <td data-label="VAT">KSh {{ number_format($item->vat_amount, 0) }}</td>
                            <td data-label="Subtotal">KSh {{ number_format($item->subtotal, 0) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><td colspan="5" style="text-align:right;">Subtotal</td><td>KSh {{ number_format($invoice->subtotal, 0) }}</td></tr>
                        <tr><td colspan="5" style="text-align:right;">VAT</td><td>KSh {{ number_format($invoice->vat_amount, 0) }}</td></tr>
                        @if($invoice->discount_amount > 0)
                        <tr><td colspan="5" style="text-align:right;">Discount</td><td>- KSh {{ number_format($invoice->discount_amount, 0) }}</td></tr>
                        @endif
                        <tr style="font-weight:700;"><td colspan="5" style="text-align:right;">Total</td><td>KSh {{ number_format($invoice->total, 0) }}</td></tr>
                        <tr><td colspan="5" style="text-align:right;">Paid</td><td>KSh {{ number_format($invoice->amount_paid, 0) }}</td></tr>
                        <tr style="font-weight:700;color:{{ $invoice->balance_due > 0 ? 'var(--danger)' : 'var(--green)' }};"><td colspan="5" style="text-align:right;">Balance Due</td><td>KSh {{ number_format($invoice->balance_due, 0) }}</td></tr>
                    </tfoot>
                </table>
            </div>

            {{-- Payment history --}}
            @if($invoice->payments->count())
            <div class="table-card">
                <div class="card-body"><h3>Payment History</h3></div>
                <table class="table">
                    <thead><tr><th>Date</th><th>Method</th><th>Amount</th><th>Reference</th><th>By</th></tr></thead>
                    <tbody>
                        @foreach($invoice->payments as $pay)
                        <tr>
                            <td data-label="Date">{{ \Carbon\Carbon::parse($pay->paid_date)->format('d M Y') }}</td>
                            <td data-label="Method">{{ ucfirst($pay->method) }}</td>
                            <td data-label="Amount">KSh {{ number_format($pay->amount, 0) }}</td>
                            <td data-label="Reference">{{ $pay->reference ?? '—' }}</td>
                            <td data-label="By">{{ $pay->user?->name }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        {{-- Record Payment sidebar --}}
        @if(in_array($invoice->status, ['sent','partial']) && $invoice->balance_due > 0)
        <div class="table-card" style="align-self:start;">
            <div class="card-body">
                <h3 style="margin-bottom:1rem;">Record Payment</h3>
                <form method="POST" action="{{ route('invoices.payments.store', $invoice) }}">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">Amount (KSh) *</label>
                        <input type="number" name="amount" class="form-control" min="0.01"
                               max="{{ $invoice->balance_due }}" step="0.01"
                               value="{{ old('amount', $invoice->balance_due) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Method *</label>
                        <select name="method" class="form-control" required>
                            <option value="cash">Cash</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="card">Card</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Date *</label>
                        <input type="date" name="paid_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reference</label>
                        <input type="text" name="reference" class="form-control" placeholder="e.g. M-Pesa ref">
                    </div>
                    <button type="submit" class="btn btn--success" style="width:100%;">Record Payment</button>
                </form>
            </div>
        </div>
        @endif
    </div>

    {{-- Attachments --}}
    <div class="table-card" style="margin-top:1.5rem;">
        <div class="card-body">
            @include('partials.attachments', ['modelType' => 'Invoice', 'modelId' => $invoice->id])
        </div>
    </div>
</div>

{{-- eTIMS Status Card --}}
@if($invoice->etims_status)
<div class="table-card" style="margin-top:1.5rem;">
    <div class="card-body">
        <h3 style="margin-bottom:1rem;">KRA eTIMS Status</h3>
        @if($invoice->etims_status === 'submitted')
            <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                <span class="badge badge-success">Submitted</span>
                @if($invoice->etims_cuin)
                    <span style="font-size:0.85rem;color:var(--color-text-muted);">CUIN: <strong style="color:var(--color-text);">{{ $invoice->etims_cuin }}</strong></span>
                @endif
                @if($invoice->etims_submitted_at)
                    <span style="font-size:0.85rem;color:var(--color-text-muted);">Submitted: {{ $invoice->etims_submitted_at->format('d M Y H:i') }}</span>
                @endif
            </div>
        @elseif($invoice->etims_status === 'pending')
            <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                <span class="badge badge-secondary">Pending</span>
                <span style="font-size:0.85rem;color:var(--color-text-muted);">Submission is queued and will process shortly.</span>
            </div>
        @elseif($invoice->etims_status === 'failed')
            <div>
                <div style="display:flex;align-items:center;gap:1rem;margin-bottom:0.75rem;flex-wrap:wrap;">
                    <span class="badge badge-danger">Failed</span>
                    @if(isset($invoice->etims_response['resultMsg']))
                        <span style="font-size:0.85rem;color:var(--color-danger);">{{ $invoice->etims_response['resultMsg'] }}</span>
                    @elseif(isset($invoice->etims_response['error']))
                        <span style="font-size:0.85rem;color:var(--color-danger);">{{ $invoice->etims_response['error'] }}</span>
                    @endif
                </div>
                <form method="POST" action="{{ route('etims.resubmit') }}" style="display:inline;">
                    @csrf
                    <input type="hidden" name="type" value="invoice">
                    <input type="hidden" name="id" value="{{ $invoice->id }}">
                    <button type="submit" class="btn btn-warning">Resubmit to eTIMS</button>
                </form>
            </div>
        @endif
    </div>
</div>
@endif
@endsection
