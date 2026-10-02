@extends('layouts.app')
@section('title', $sale->invoice_number)

@push('styles')
<style>
/* Invoice document — modernized: card treatment (radius + shadow), app
   color tokens throughout (was all hardcoded hex — now themeable and
   consistent with the rest of the app), and a proper mobile card-stack
   for the items table instead of just hiding a column and hoping the
   rest fits (was the previous approach). */
.invoice-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 1.75rem;
    padding-bottom: 1.25rem;
    border-bottom: 1px solid var(--color-border);
}

.invoice-wrap {
    max-width: 860px;
    margin: 0 auto;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg, 14px);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    color: var(--color-text);
    font-size: 0.875rem;
    line-height: 1.5;
}

/* Colored top accent reflecting the invoice's own status — a small touch
   that makes the status legible at a glance before reading anything. */
.invoice-wrap { border-top: 4px solid var(--inv-accent, var(--color-text-muted)); }
.invoice-wrap.status-paid      { --inv-accent: var(--color-success); }
.invoice-wrap.status-partial   { --inv-accent: var(--color-warning); }
.invoice-wrap.status-unpaid    { --inv-accent: var(--color-danger); }
.invoice-wrap.status-cancelled { --inv-accent: var(--color-text-muted); }

.inv-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1.5rem;
    padding: 2rem 2.25rem 1.75rem;
    border-bottom: 1px solid var(--color-border);
}

.inv-head-left .biz-name {
    font-family: var(--font-heading);
    font-size: 1.4rem;
    font-weight: 800;
    letter-spacing: -0.03em;
    color: var(--color-text);
    margin-bottom: 6px;
}

.inv-head-left .biz-detail {
    font-size: 0.8rem;
    color: var(--color-text-muted);
    line-height: 1.7;
}

.inv-head-right {
    text-align: right;
    flex-shrink: 0;
}

.inv-label {
    font-family: var(--font-heading);
    font-size: 1.6rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    color: var(--color-text-light, var(--color-text-muted));
    display: block;
    line-height: 1;
    margin-bottom: 12px;
}

.inv-meta-table {
    font-size: 0.8rem;
    border-collapse: collapse;
    margin-left: auto;
}

.inv-meta-table td {
    padding: 2px 0 2px 16px;
}

.inv-meta-table .meta-key {
    color: var(--color-text-muted);
    padding-left: 0;
    text-align: left;
    white-space: nowrap;
}

.inv-meta-table .meta-val {
    font-weight: 600;
    color: var(--color-text);
    text-align: right;
}

/* Status pill — soft filled badge instead of the previous outlined
   "rubber stamp" look, matching the modern pill treatment used for
   status chips elsewhere in the app. */
.inv-status-stamp {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--inv-accent, var(--color-text-muted)) 14%, transparent);
    color: var(--inv-accent, var(--color-text-muted));
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 12px;
}
.inv-status-stamp::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
    flex-shrink: 0;
}

/* Bill-to / payment row */
.inv-parties {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 0;
    background: var(--color-surface-2);
    border-bottom: 1px solid var(--color-border);
}

.inv-party {
    padding: 1.25rem 2.25rem;
    border-right: 1px solid var(--color-border);
}

.inv-party:last-child { border-right: none; }

.inv-party-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--color-text-muted);
    margin-bottom: 8px;
}

.inv-party-name {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--color-text);
    margin-bottom: 4px;
}

.inv-party-detail {
    font-size: 0.78rem;
    color: var(--color-text-muted);
    line-height: 1.7;
}

/* Items table */
.inv-items-wrap { padding: 0.5rem 2.25rem 0; overflow-x: auto; -webkit-overflow-scrolling: touch; }

.inv-items {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.84rem;
}

.inv-items thead tr {
    border-bottom: 2px solid var(--color-text);
}

.inv-items thead th {
    padding: 10px 12px;
    text-align: left;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--color-text-muted);
    white-space: nowrap;
}

.inv-items thead th:not(:first-child) { text-align: right; }
.inv-items thead th:nth-child(2)      { text-align: left; }

.inv-items tbody tr {
    border-bottom: 1px solid var(--color-border);
    transition: background .1s;
}

.inv-items tbody tr:hover { background: var(--color-surface-2); }

.inv-items tbody td {
    padding: 12px;
    color: var(--color-text);
    vertical-align: top;
}

.inv-items tbody td:not(:first-child) { text-align: right; }
.inv-items tbody td:nth-child(2)      { text-align: left; }

.inv-item-name {
    font-weight: 600;
    color: var(--color-text);
}

.inv-item-sku {
    font-size: 0.72rem;
    color: var(--color-text-muted);
    margin-top: 2px;
}

/* Totals — set apart in its own shaded panel so it reads as the page's
   focal point rather than just more table rows. */
.inv-totals {
    display: flex;
    justify-content: flex-end;
    padding: 1.5rem 2.25rem 2rem;
}

.inv-totals-card {
    width: 300px;
    max-width: 100%;
    background: var(--color-surface-2);
    border-radius: var(--radius-md, 10px);
    padding: 1rem 1.25rem;
    box-sizing: border-box;
}

.tot-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 1.5rem;
    font-size: 0.84rem;
    padding: 5px 0;
}

.tot-label {
    color: var(--color-text-muted);
}

.tot-value {
    text-align: right;
    font-weight: 600;
    color: var(--color-text);
    flex-shrink: 0;
}

.tot-row.tot-divider {
    border-top: 1px solid var(--color-border-2);
    padding-top: 8px;
    margin-top: 4px;
}

.tot-row.tot-grand {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--color-text);
    padding-top: 10px;
    margin-top: 2px;
    border-top: 2px solid var(--color-text);
}

.tot-row.tot-paid    .tot-value { color: var(--color-success); }
.tot-row.tot-balance .tot-value {
    color: var(--color-danger);
    font-weight: 800;
}

.tot-row.tot-balance-zero .tot-value { color: var(--color-success); }

/* Footer */
.inv-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 1.1rem 2.25rem;
    font-size: 0.75rem;
    color: var(--color-text-muted);
    background: var(--color-surface-2);
    border-top: 1px solid var(--color-border);
}

.inv-footer-note {
    font-style: italic;
}

.inv-footer-served {
    text-align: right;
    white-space: nowrap;
}

@media print {
    .invoice-actions { display: none; }
    .invoice-wrap {
        border: none;
        box-shadow: none;
        border-radius: 0;
        margin: 0;
        max-width: 100%;
    }
    body { background: #fff; }
    .sidebar, .topbar, .mobile-header { display: none !important; }
    .main-content { margin: 0; padding: 0; }
    .page { padding: 0; }
}

/* ── Tablet / mobile ──────────────────────────────────────
   Matches the app-wide 768px breakpoint. Below it, the generic
   table→card-stack rule in responsive.css already turns .inv-items
   (a real per-row data grid) into a labeled stack — no per-column
   hide-and-hope needed here any more, so every column stays visible.
   The totals block is flex rows (not a table), so it just reflows
   naturally without needing that treatment. */
@media (max-width: 768px) {
    .inv-head { flex-direction: column; gap: 1.25rem; padding: 1.5rem; }
    .inv-head-right { text-align: left; width: 100%; }
    .inv-meta-table { margin-left: 0; width: 100%; }
    .inv-meta-table .meta-val { text-align: right; }
    .inv-parties { grid-template-columns: 1fr; }
    .inv-party { border-right: none; border-bottom: 1px solid var(--color-border); padding: 1.1rem 1.5rem; }
    .inv-items-wrap { padding: 0 1.5rem; }
    .inv-totals { padding: 1.25rem 1.5rem 1.5rem; }
    .inv-totals-card { width: 100%; padding: 1rem; }
    .tot-row { font-size: 0.8rem; }
    .inv-footer { flex-direction: column; align-items: flex-start; gap: 4px; padding: 1rem 1.5rem; }
    .inv-footer-served { text-align: left; }
}
</style>
@endpush

@section('content')
<div class="page" style="max-width: 900px;">

{{-- Action bar --}}
<div class="invoice-actions">
    <a href="{{ route('sales.invoice', $sale) }}" class="btn btn--primary" target="_blank">Print Invoice</a>
    <a href="{{ route('sales.receipt', $sale) }}" class="btn btn--outline" target="_blank">&#128424; Print Receipt</a>
    @role('owner','overall_manager','manager','cashier')
    @if($sale->customer && ($sale->customer->phone || $sale->customer->email) && $sale->payment_status === 'paid')
    <form method="POST" action="{{ route('sales.send-receipt', $sale) }}" style="display:inline;">
        @csrf
        <button type="submit" class="btn btn--outline">&#128241; Send Receipt</button>
    </form>
    @endif
    @endrole
    @role('owner','overall_manager','manager','cashier')
    @if(in_array($sale->payment_status, ['unpaid','partial']) && $sale->sale_status !== 'cancelled')
    <a href="{{ route('sales.payment', $sale) }}" class="btn btn--primary" style="background:var(--color-success);border-color:var(--color-success);">
        &#128178; Record Payment
    </a>
    @endif
    @endrole
    @role('owner','manager')
    @if($sale->customer && $sale->customer->email)
    <form method="POST" action="{{ route('sales.email-invoice', $sale) }}"
          onsubmit="return confirm('Send invoice to {{ addslashes($sale->customer->email) }}?')">
        @csrf
        <button type="submit" class="btn btn--outline">Email Invoice</button>
    </form>
    @endif
    {{-- The Sale Returns feature (fixed earlier this session) has had no
    entry point anywhere in the app — SaleReturnController::create() requires
    a sale_id, so this is the only place a return could ever have started
    from, and no link existed here or anywhere else. --}}
    @if($sale->sale_status === 'completed')
    <a href="{{ route('returns.create', ['sale_id' => $sale->id]) }}" class="btn btn--outline">
        &#8617; Process Return
    </a>
    @endif
    @if($sale->sale_status !== 'cancelled')
    @if($voidReasons->isEmpty())
    <a href="{{ route('settings.void-reasons.index') }}" class="btn btn--outline" title="Add a void reason first">Cancel Sale (add a reason first)</a>
    @else
    <button type="button" class="btn btn--danger" onclick="document.getElementById('cancelSaleModal').style.display='flex'">Cancel Sale</button>
    @endif
    @endif
    <a href="{{ route('sales.index') }}" class="btn btn--outline" style="margin-left: auto;">Back to Sales</a>
    @endrole
    @cashier
    @if($sale->sale_status !== 'cancelled')
        @if($sale->voidRequests->isNotEmpty())
        <span class="btn btn--outline" style="pointer-events:none;">Void requested — awaiting manager approval</span>
        @elseif($voidReasons->isEmpty())
        <span class="btn btn--outline" style="pointer-events:none;" title="Ask a manager to add a void reason">Void unavailable — no reasons configured</span>
        @else
        <button type="button" class="btn btn--danger" onclick="document.getElementById('proposeVoidModal').style.display='flex'">Propose Void</button>
        @endif
    @endif
    <a href="{{ route('dashboard') }}" class="btn btn--outline" style="margin-left: auto;">Back</a>
    @endcashier
</div>

@role('owner','overall_manager','manager')
@if($sale->sale_status !== 'cancelled' && $voidReasons->isNotEmpty())
<div id="cancelSaleModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:1000;">
    <div style="background:var(--color-surface);border-radius:8px;padding:1.5rem;max-width:420px;width:90%;">
        <h3 style="margin-top:0;">Cancel Sale {{ $sale->invoice_number }}</h3>
        <p style="font-size:0.85rem;color:var(--color-text-muted);">Stock will be restored. This is recorded immediately as an approved void on your account.</p>
        <form method="POST" action="{{ route('sales.cancel', $sale) }}">
            @csrf @method('PATCH')
            <div class="form-group">
                <label class="form-label">Reason *</label>
                <select name="void_reason_id" class="form-control" required>
                    <option value="">Select a reason...</option>
                    @foreach($voidReasons as $reason)
                    <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Note (optional)</label>
                <textarea name="note" class="form-control" rows="2" maxlength="500"></textarea>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:1rem;">
                <button type="button" class="btn btn--outline" onclick="document.getElementById('cancelSaleModal').style.display='none'">Back</button>
                <button type="submit" class="btn btn--danger">Cancel Sale</button>
            </div>
        </form>
    </div>
</div>
@endif
@endrole

@cashier
@if($sale->sale_status !== 'cancelled' && $sale->voidRequests->isEmpty() && $voidReasons->isNotEmpty())
<div id="proposeVoidModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:1000;">
    <div style="background:var(--color-surface);border-radius:8px;padding:1.5rem;max-width:420px;width:90%;">
        <h3 style="margin-top:0;">Propose Void — {{ $sale->invoice_number }}</h3>
        <p style="font-size:0.85rem;color:var(--color-text-muted);">A manager must approve this before the sale is actually cancelled.</p>
        <form method="POST" action="{{ route('sales.void-request.store', $sale) }}">
            @csrf
            <div class="form-group">
                <label class="form-label">Reason *</label>
                <select name="void_reason_id" class="form-control" required>
                    <option value="">Select a reason...</option>
                    @foreach($voidReasons as $reason)
                    <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Note (optional)</label>
                <textarea name="note" class="form-control" rows="2" maxlength="500"></textarea>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:1rem;">
                <button type="button" class="btn btn--outline" onclick="document.getElementById('proposeVoidModal').style.display='none'">Back</button>
                <button type="submit" class="btn btn--danger">Submit Request</button>
            </div>
        </form>
    </div>
</div>
@endif
@endcashier

@php
    $statusClass = match(true) {
        $sale->sale_status === 'cancelled'    => 'cancelled',
        $sale->payment_status === 'paid'      => 'paid',
        $sale->payment_status === 'partial'   => 'partial',
        default                               => 'unpaid',
    };
    $statusText = match(true) {
        $sale->sale_status === 'cancelled'    => 'Cancelled',
        $sale->payment_status === 'paid'      => 'Paid',
        $sale->payment_status === 'partial'   => 'Partial',
        default                               => 'Unpaid',
    };
@endphp

{{-- Invoice document --}}
<div class="invoice-wrap status-{{ $statusClass }}">

    {{-- Header --}}
    <div class="inv-head">
        <div class="inv-head-left">
            <div class="biz-name">{{ $sale->business->name }}</div>
            <div class="biz-detail">
                @if($sale->business->address){{ $sale->business->address }}<br>@endif
                @if($sale->business->phone){{ $sale->business->phone }}<br>@endif
                @if($sale->business->email){{ $sale->business->email }}@endif
            </div>
        </div>
        <div class="inv-head-right">
            <span class="inv-status-stamp">{{ $statusText }}</span>
            <span class="inv-label">Invoice</span>
            <table class="inv-meta-table table-plain">
                <tr>
                    <td class="meta-key">Invoice No.</td>
                    <td class="meta-val">{{ $sale->invoice_number }}</td>
                </tr>
                <tr>
                    <td class="meta-key">Date</td>
                    <td class="meta-val">{{ $sale->created_at->format('d M Y') }}</td>
                </tr>
                <tr>
                    <td class="meta-key">Time</td>
                    <td class="meta-val">{{ $sale->created_at->format('g:i A') }}</td>
                </tr>
                <tr>
                    <td class="meta-key">Payment</td>
                    <td class="meta-val">{{ ucfirst(str_replace('_', ' ', $sale->payment_method)) }}</td>
                </tr>
                @if($sale->mpesa_reference)
                <tr>
                    <td class="meta-key">M-Pesa Ref</td>
                    <td class="meta-val">
                        {{ $sale->mpesa_reference }}
                        @php
                            $codeChecks = \App\Models\AuditLog::where('event', 'sale.payment')
                                ->where('subject_type', \App\Models\Sale::class)->where('subject_id', $sale->id)
                                ->get()->map(fn ($l) => $l->metadata['code_verified'] ?? null)->filter(fn ($v) => $v !== null);
                        @endphp
                        @php
                            $stkConfirmed = \App\Models\MpesaTransaction::where('sale_id', $sale->id)->where('status', 'COMPLETE')->exists();
                        @endphp
                        @php $svCheck = \Illuminate\Support\Facades\Schema::hasTable('mpesa_status_checks') ? \App\Models\MpesaStatusCheck::latestForSale($sale->id) : null; @endphp
                        @if($svCheck && $svCheck->status === 'confirmed')
                            <span class="badge badge-success" title="Safaricom confirmed this receipt code{{ $svCheck->confirmed_amount ? ' for KSh '.number_format($svCheck->confirmed_amount, 2) : '' }}.">Verified by Safaricom</span>
                        @elseif($svCheck && $svCheck->status === 'pending')
                            <span class="badge badge-secondary" title="Waiting for Safaricom's answer — refresh in a moment.">Verifying&hellip;</span>
                        @elseif($svCheck && $svCheck->status === 'mismatch')
                            <span class="badge badge-danger" title="{{ $svCheck->result_desc }}">Amount doesn't match</span>
                        @elseif($svCheck && $svCheck->status === 'not_found')
                            <span class="badge badge-danger" title="{{ $svCheck->result_desc }}">Code not found</span>
                        @elseif($codeChecks->contains(false) && $stkConfirmed)
                            <span class="badge badge-success" title="Safaricom confirmed the M-Pesa push for this sale, but the code typed in could not be matched to its receipt.">Payment confirmed</span>
                        @elseif($codeChecks->contains(false))
                            <span class="badge badge-warning" title="This code was typed in by hand and no matching M-Pesa payment was found in the system.">Not verified</span>
                        @elseif($codeChecks->contains(true))
                            <span class="badge badge-success" title="Matched a payment the system received.">Verified</span>
                        @endif
                        @if($codeChecks->contains(false) && (!$svCheck || in_array($svCheck->status, ['failed', 'not_found', 'mismatch'], true)) && \App\Http\Controllers\MpesaStatusController::isAvailable($sale->business))
                            <form method="POST" action="{{ route('sales.verify-mpesa', $sale) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-outline btn--sm" style="padding:1px 8px; font-size:11px;">Verify with Safaricom</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @endif
            </table>
        </div>
    </div>

    {{-- Bill To / Served By --}}
    <div class="inv-parties">
        <div class="inv-party">
            <div class="inv-party-label">Bill To</div>
            @if($sale->customer)
                <div class="inv-party-name">{{ $sale->customer->name }}</div>
                <div class="inv-party-detail">
                    @if($sale->customer->phone){{ $sale->customer->phone }}<br>@endif
                    @if($sale->customer->email){{ $sale->customer->email }}<br>@endif
                    @if($sale->customer->address){{ $sale->customer->address }}@endif
                </div>
            @else
                <div class="inv-party-name">Walk-in Customer</div>
            @endif
        </div>

        <div class="inv-party">
            <div class="inv-party-label">Served By</div>
            @if($sale->onlineOrder)
            <div class="inv-party-name">Online Order</div>
            <div class="inv-party-detail">Self-checkout</div>
            @else
            <div class="inv-party-name">{{ $sale->user->name }}</div>
            <div class="inv-party-detail">{{ $sale->user->roleLabel() }}</div>
            @endif
        </div>

        <div class="inv-party">
            <div class="inv-party-label">Payment Summary</div>
            <div class="inv-party-detail" style="line-height: 2;">
                <span style="color:var(--color-text-muted);">Total:</span>
                <strong style="float:right; color:var(--color-text);">KSh {{ number_format($sale->total_amount, 2) }}</strong><br>
                <span style="color:var(--color-text-muted);">Paid:</span>
                <strong style="float:right; color:var(--color-success);">KSh {{ number_format($sale->paid_amount, 2) }}</strong><br>
                <span style="color:var(--color-text-muted);">Balance:</span>
                <strong style="float:right; color:var({{ $sale->balance_due > 0 ? '--color-danger' : '--color-success' }});">
                    KSh {{ number_format($sale->balance_due, 2) }}
                </strong>
            </div>
        </div>
    </div>

    {{-- Items --}}
    <div class="inv-items-wrap">
    <table class="inv-items">
        <thead>
            <tr>
                <th style="width:36px;">#</th>
                <th>Description</th>
                <th style="width:80px;">Qty</th>
                <th style="width:120px;">Unit Price</th>
                <th style="width:80px;">Disc.</th>
                <th style="width:120px;">Amount</th>
                @role('owner','manager')
                <th style="width:110px;">Profit</th>
                @endrole
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $i => $item)
            <tr>
                <td data-label="#" style="color:#888; text-align:left;">{{ $i + 1 }}</td>
                <td data-label="Description">
                    <div class="inv-item-name">{{ $item->product_name }}</div>
                    @if($item->product?->sku)
                        <div class="inv-item-sku">SKU: {{ $item->product->sku }}</div>
                    @endif
                </td>
                <td data-label="Qty">{{ $item->quantity }}</td>
                <td data-label="Unit Price">KSh {{ number_format($item->unit_price, 2) }}</td>
                <td data-label="Disc.">{{ $item->discount > 0 ? $item->discount . '%' : '—' }}</td>
                <td data-label="Amount"><strong>KSh {{ number_format($item->subtotal, 2) }}</strong></td>
                @role('owner','manager')
                <td data-label="Profit" style="color:#166534;">KSh {{ number_format($item->lineProfit(), 2) }}</td>
                @endrole
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>

    {{-- Totals — flex rows (not a table) so long amounts simply wrap or
         shrink normally instead of fighting table auto/fixed-layout sizing
         on narrow screens. --}}
    <div class="inv-totals">
        <div class="inv-totals-card">
            <div class="tot-row">
                <span class="tot-label">Subtotal</span>
                <span class="tot-value">KSh {{ number_format($sale->subtotal, 2) }}</span>
            </div>
            @if(($sale->delivery_fee ?? 0) > 0)
            <div class="tot-row">
                <span class="tot-label">Delivery Fee</span>
                <span class="tot-value">KSh {{ number_format($sale->delivery_fee, 2) }}</span>
            </div>
            @endif
            @if($sale->discount_amount > 0)
            <div class="tot-row">
                <span class="tot-label">Discount</span>
                <span class="tot-value" style="color:var(--color-danger);">− KSh {{ number_format($sale->discount_amount, 2) }}</span>
            </div>
            @endif
            @php $promo = $sale->promoDiscounts(); @endphp
            @if($promo['coupon'] > 0)
            <div class="tot-row">
                <span class="tot-label">Coupon</span>
                <span class="tot-value" style="color:var(--color-danger);">− KSh {{ number_format($promo['coupon'], 2) }}</span>
            </div>
            @endif
            @if($promo['loyalty'] > 0)
            <div class="tot-row">
                <span class="tot-label">Loyalty points ({{ number_format($promo['points']) }} pts)</span>
                <span class="tot-value" style="color:var(--color-danger);">− KSh {{ number_format($promo['loyalty'], 2) }}</span>
            </div>
            @endif
            @if($sale->tax_amount > 0)
            <div class="tot-row">
                <span class="tot-label" style="color:var(--color-text-muted);">VAT ({{ $sale->business->vat_rate ?? 16 }}%) <small style="font-size:10px;">incl.</small></span>
                <span class="tot-value" style="color:var(--color-text-muted);">KSh {{ number_format($sale->tax_amount, 2) }}</span>
            </div>
            @endif
            <div class="tot-row tot-grand">
                <span class="tot-label" style="font-weight:800; color:var(--color-text);">Total</span>
                <span class="tot-value">KSh {{ number_format($sale->total_amount, 2) }}</span>
            </div>
            <div class="tot-row tot-paid">
                <span class="tot-label">Amount Paid</span>
                <span class="tot-value">KSh {{ number_format($sale->paid_amount, 2) }}</span>
            </div>
            @if($sale->balance_due > 0)
            <div class="tot-row tot-balance">
                <span class="tot-label">Balance Due</span>
                <span class="tot-value">KSh {{ number_format($sale->balance_due, 2) }}</span>
            </div>
            @else
            <div class="tot-row tot-balance-zero">
                <span class="tot-label">Balance Due</span>
                <span class="tot-value">KSh 0.00</span>
            </div>
            @endif
            @role('owner','manager')
            <div class="tot-row tot-divider">
                <span class="tot-label" style="color:var(--color-text-muted); font-size:0.75rem;">Total Profit</span>
                <span class="tot-value" style="color:var(--color-success); font-size:0.75rem;">KSh {{ number_format($sale->totalProfit(), 2) }}</span>
            </div>
            @endrole
        </div>
    </div>

    {{-- Footer --}}
    <div class="inv-footer">
        <div class="inv-footer-note">
            @if($sale->notes)
                {{ $sale->notes }}
            @else
                Thank you for your business.
            @endif
        </div>
        <div class="inv-footer-served">
            {{ $sale->business->name }} &bull; {{ now()->format('Y') }}
        </div>
    </div>

</div>{{-- /.invoice-wrap --}}

<div class="table-card" style="margin-top:1.5rem;padding:1.25rem;">
    @include('partials.attachments', ['modelType' => 'Sale', 'modelId' => $sale->id])
</div>
</div>
@include('partials.etims-refund-status', ['etimsType' => 'sale_cancel', 'etimsId' => $sale->id])
@endsection
