<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ $invoice->invoice_number }}</title>
<style>
/* Standalone page (no shared layout) — Poppins self-hosted here rather
   than relying on main.css's @font-face.
   NOTE: this same view is also rendered via DomPDF (customer-portal PDF
   download, see CustomerPortalController@downloadInvoice) as well as
   opened as a plain browser page from the main app (InvoiceController@pdf).
   DomPDF has no reliable flexbox/grid support, so this layout is built
   entirely with display:table/table-cell — safe in both browsers and
   DomPDF — rather than the flex/grid it used before, which would have
   silently collapsed in the actual downloaded PDF. */
@font-face {
    font-family: 'Poppins';
    src: url('/fonts/poppins/poppins-latin-400-normal.woff2') format('woff2');
    font-weight: 400; font-style: normal; font-display: swap;
}
@font-face {
    font-family: 'Poppins';
    src: url('/fonts/poppins/poppins-latin-700-normal.woff2') format('woff2');
    font-weight: 700; font-style: normal; font-display: swap;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Poppins', 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 13px; color: #1a1a2e; padding: 40px; }

/* ── Header ── */
.header { display: table; width: 100%; border-bottom: 1px solid #e5e7eb; padding-bottom: 22px; margin-bottom: 28px; }
.header-left, .header-right { display: table-cell; vertical-align: top; }
.header-right { text-align: right; }
.business-name { font-size: 20px; font-weight: 700; color: #0f766e; margin-bottom: 4px; }
.business-detail { color: #666; font-size: 12px; line-height: 1.6; }
.invoice-title { font-size: 26px; font-weight: 700; color: #0f766e; letter-spacing: 1.5px; margin-bottom: 6px; }
.invoice-number { color: #888; font-size: 13px; margin-bottom: 8px; }
.inv-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-weight: 700; font-size: 11px; letter-spacing: 0.5px; text-transform: uppercase; }
.inv-badge-paid    { background: #d1fae5; color: #065f46; }
.inv-badge-partial { background: #fef3c7; color: #92400e; }
.inv-badge-sent,
.inv-badge-draft,
.inv-badge-cancelled { background: #e5e7eb; color: #374151; }
.inv-badge-overdue,
.inv-badge-unpaid  { background: #fee2e2; color: #991b1b; }

/* ── Meta (Bill To / dates) ── */
.meta-grid { display: table; width: 100%; margin-bottom: 26px; }
.meta-col { display: table-cell; width: 50%; vertical-align: top; }
.meta-block small { display: block; font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.6px; color: #0f766e; margin-bottom: 3px; }
.meta-block strong { display: block; font-size: 14px; }
.meta-dates { display: table; width: 100%; }
.meta-dates-item { display: table-cell; padding-right: 24px; }

/* ── Items table ── */
table.items { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
table.items thead tr { background: #0f766e; color: #fff; }
table.items thead th { padding: 9px 10px; text-align: left; font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; }
table.items tbody td { padding: 9px 10px; border-bottom: 1px solid #f3f4f6; font-size: 12.5px; vertical-align: top; }
.text-right { text-align: right; }

/* ── Totals ── */
.totals-wrapper { text-align: right; margin-bottom: 26px; }
.totals-table { display: inline-table; min-width: 260px; }
.totals-row { display: table-row; }
.totals-label, .totals-value { display: table-cell; padding: 5px 10px; font-size: 13px; }
.totals-label { color: #555; text-align: left; }
.totals-value { text-align: right; font-weight: 600; color: #1a1a2e; }
.totals-grand .totals-label,
.totals-grand .totals-value { font-size: 15.5px; font-weight: 700; color: #0f766e; padding-top: 8px; border-top: 2px solid #0f766e; }
.totals-balance .totals-value { color: #dc2626; font-weight: 700; }

/* ── Notes / terms / bank details ── */
.info-box { margin-top: 14px; padding: 12px 15px; border-radius: 4px; font-size: 12px; color: #444; }
.info-box strong { color: #1a1a2e; }
.info-box-terms { background: #f0fdfa; border-left: 4px solid #0f766e; }
.info-box-bank  { background: #f9fafb; border-left: 4px solid #6b7280; }
.info-box pre { font-family: inherit; margin: 0; white-space: pre-wrap; }

/* ── Footer ── */
.footer { margin-top: 36px; color: #888; font-size: 11px; text-align: center; border-top: 1px solid #eee; padding-top: 14px; }

@media print { body { padding: 20px; } }
/* This is a print/PDF-styled view, but the InvoiceController route
   renders it as a normal HTML page (window.print() runs on load rather
   than a server-generated binary), so a phone opening this URL directly
   sees the raw page with none of the app's own responsive.css rules
   applying — this file never had any mobile handling of its own. */
@media screen and (max-width: 600px) {
    body { padding: 16px; }
    .header, .header-left, .header-right { display: block; width: 100%; text-align: left; }
    .header-right { margin-top: 14px; }
    .meta-grid, .meta-col { display: block; width: 100%; }
    .meta-col + .meta-col { margin-top: 14px; }
    .table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    table.items { min-width: 480px; }
}
</style>
</head>
<body>
<div class="header">
    <div class="header-left">
        @if($business->show_logo_on_invoice && $business->logo)
        <div style="margin-bottom:8px;"><img src="{{ asset('storage/' . $business->logo) }}" alt="{{ $business->name }}" style="max-height:60px;max-width:180px;"></div>
        @endif
        <div class="business-name">{{ $business->name }}</div>
        <div class="business-detail">
            @if($business->address){{ $business->address }}@if($business->city), {{ $business->city }}@endif<br>@endif
            @if($business->phone){{ $business->phone }}<br>@endif
            @if($business->email){{ $business->email }}<br>@endif
            @if($business->isVatRegistered())VAT No: {{ $business->vat_number }}@endif
        </div>
    </div>
    <div class="header-right">
        <div class="invoice-title">INVOICE</div>
        <div class="invoice-number">{{ $invoice->invoice_number }}</div>
        <span class="inv-badge inv-badge-{{ strtolower($invoice->status) }}">{{ strtoupper($invoice->status) }}</span>
    </div>
</div>

<div class="meta-grid">
    <div class="meta-col">
        <div class="meta-block"><small>Bill To</small><strong>{{ $invoice->customer?->name ?? 'Customer' }}</strong></div>
    </div>
    <div class="meta-col">
        <div class="meta-dates">
            <div class="meta-dates-item meta-block"><small>Issue Date</small><strong>{{ $invoice->issue_date->format('d M Y') }}</strong></div>
            <div class="meta-dates-item meta-block"><small>Due Date</small><strong>{{ $invoice->due_date->format('d M Y') }}</strong></div>
        </div>
    </div>
</div>

<div class="table-scroll">
<table class="items">
    <thead>
        <tr>
            <th>Description</th>
            <th class="text-right">Qty</th>
            <th class="text-right">Unit Price</th>
            @if($business->isVatRegistered())<th class="text-right">VAT %</th><th class="text-right">VAT</th>@endif
            <th class="text-right">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach($invoice->items as $item)
        <tr>
            <td>{{ $item->description }}</td>
            <td class="text-right">{{ $item->quantity }}</td>
            <td class="text-right">KSh {{ number_format($item->unit_price, 0) }}</td>
            @if($business->isVatRegistered())
            <td class="text-right">{{ $item->vat_rate }}%</td>
            <td class="text-right">KSh {{ number_format($item->vat_amount, 0) }}</td>
            @endif
            <td class="text-right">KSh {{ number_format($item->subtotal, 0) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
</div>

<div class="totals-wrapper">
    <div class="totals-table">
        <div class="totals-row">
            <div class="totals-label">Subtotal</div>
            <div class="totals-value">KSh {{ number_format($invoice->subtotal, 0) }}</div>
        </div>
        @if($business->isVatRegistered())
        <div class="totals-row">
            <div class="totals-label">VAT</div>
            <div class="totals-value">KSh {{ number_format($invoice->vat_amount, 0) }}</div>
        </div>
        @endif
        @if($invoice->discount_amount > 0)
        <div class="totals-row">
            <div class="totals-label">Discount</div>
            <div class="totals-value">- KSh {{ number_format($invoice->discount_amount, 0) }}</div>
        </div>
        @endif
        <div class="totals-row totals-grand">
            <div class="totals-label">Total Due</div>
            <div class="totals-value">KSh {{ number_format($invoice->total, 0) }}</div>
        </div>
        @if($invoice->amount_paid > 0)
        <div class="totals-row">
            <div class="totals-label">Paid</div>
            <div class="totals-value">KSh {{ number_format($invoice->amount_paid, 0) }}</div>
        </div>
        <div class="totals-row totals-balance">
            <div class="totals-label">Balance Due</div>
            <div class="totals-value">KSh {{ number_format($invoice->balance_due, 0) }}</div>
        </div>
        @endif
    </div>
</div>

@if($invoice->notes)
<p style="color:#555;margin-bottom:8px;"><strong>Notes:</strong> {{ $invoice->notes }}</p>
@endif

@if($invoice->payment_terms)
<p style="color:#555;margin-bottom:8px;"><strong>Payment Terms:</strong> {{ $invoice->payment_terms }}</p>
@endif

@if($business->invoice_terms)
<div class="info-box info-box-terms">
    <strong>Terms &amp; Conditions:</strong><br>{{ $business->invoice_terms }}
</div>
@endif

@if($business->invoice_bank_details)
<div class="info-box info-box-bank">
    <strong>Payment Details:</strong><br><pre>{{ $business->invoice_bank_details }}</pre>
</div>
@endif

<div class="footer">
    {{ $business->name }} &mdash; Thank you for your business.
</div>

<script>window.onload = function() { window.print(); }</script>
</body>
</html>
