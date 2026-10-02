<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $sale->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 13px;
            color: #1a1a2e;
            background: #ffffff;
            padding: 30px;
        }

        /* ── Header ── */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 32px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 22px;
        }
        .header-left  { display: table-cell; width: 60%; vertical-align: top; }
        .header-right { display: table-cell; width: 40%; vertical-align: top; text-align: right; }

        .business-name {
            font-size: 22px;
            font-weight: bold;
            color: #0f766e;
            margin-bottom: 4px;
        }
        .business-detail { color: #555; font-size: 12px; line-height: 1.6; }

        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            color: #0f766e;
            letter-spacing: 2px;
            margin-bottom: 8px;
        }
        .invoice-meta { font-size: 12px; color: #555; line-height: 1.8; }
        .invoice-meta strong { color: #1a1a2e; }

        /* ── Status badge ── */
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-paid    { background: #d1fae5; color: #065f46; }
        .badge-partial { background: #fef3c7; color: #92400e; }
        .badge-unpaid  { background: #fee2e2; color: #991b1b; }

        /* ── Parties ── */
        .parties {
            display: table;
            width: 100%;
            margin-bottom: 25px;
        }
        .party { display: table-cell; width: 50%; vertical-align: top; }
        .party h4 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #0f766e;
            margin-bottom: 6px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
        }
        .party p { font-size: 12px; color: #444; line-height: 1.7; }
        .party p strong { color: #1a1a2e; font-size: 13px; }

        /* ── Items table ── */
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.items thead tr {
            background: #0f766e;
            color: white;
        }
        table.items thead th {
            padding: 9px 10px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
        }
        table.items thead th.right { text-align: right; }
        table.items tbody tr { border-bottom: 1px solid #f3f4f6; }
        table.items tbody td {
            padding: 9px 10px;
            font-size: 12px;
            color: #333;
            vertical-align: top;
        }
        table.items tbody td.right { text-align: right; }

        /* ── Totals ── */
        .totals-wrapper { text-align: right; margin-bottom: 25px; }
        .totals-table { display: inline-table; min-width: 260px; }
        .totals-row {
            display: table-row;
        }
        .totals-label, .totals-value {
            display: table-cell;
            padding: 5px 10px;
            font-size: 13px;
        }
        .totals-label { color: #555; text-align: left; }
        .totals-value { text-align: right; font-weight: 600; color: #1a1a2e; }
        .totals-divider { border-top: 1px solid #e5e7eb; }
        .totals-grand .totals-label,
        .totals-grand .totals-value {
            font-size: 15px;
            font-weight: bold;
            color: #0f766e;
            padding-top: 8px;
            border-top: 2px solid #0f766e;
        }
        .totals-discount .totals-value { color: #dc2626; }
        .totals-paid .totals-value     { color: #059669; }
        .totals-balance .totals-value  { color: #dc2626; }

        /* ── Payment info ── */
        .payment-info {
            background: #f0fdfa;
            border-left: 4px solid #0f766e;
            border-radius: 4px;
            padding: 12px 15px;
            margin-bottom: 25px;
            font-size: 12px;
            color: #444;
        }
        .payment-info strong { color: #1a1a2e; }

        .mpesa-box {
            background: #f0fdf4;
            border-left: 4px solid #16a34a;
            border-radius: 4px;
            padding: 10px 15px;
            margin-top: 10px;
        }
        .mpesa-box p.title {
            font-weight: bold;
            color: #15803d;
            font-size: 12px;
            margin-bottom: 6px;
        }
        table.mpesa-detail { width: 100%; border-collapse: collapse; }
        table.mpesa-detail td {
            padding: 3px 0;
            font-size: 11px;
        }
        table.mpesa-detail td.label { color: #555; width: 40%; }
        table.mpesa-detail td.value { font-weight: 600; color: #1a1a2e; }

        /* ── Footer ── */
        .footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 15px;
            text-align: center;
            font-size: 11px;
            color: #888;
            line-height: 1.6;
        }
    </style>
</head>
<body>

{{-- Header --}}
<div class="header">
    <div class="header-left">
        @if($sale->business->logo)
            <img src="{{ public_path('storage/' . $sale->business->logo) }}"
                 alt="{{ $sale->business->name }}"
                 style="max-height:60px; max-width:180px; margin-bottom:8px; display:block;">
        @endif
        <div class="business-name">{{ $sale->business->name }}</div>
        <div class="business-detail">
            {{ $sale->business->phone }}<br>
            {{ $sale->business->email }}<br>
            @if($sale->business->address)
                {{ $sale->business->address }}{{ $sale->business->city ? ', ' . $sale->business->city : '' }}<br>
            @endif
            @if($sale->business->kra_pin)
                KRA PIN: {{ $sale->business->kra_pin }}
            @endif
        </div>
    </div>
    <div class="header-right">
        <div class="invoice-title">INVOICE</div>
        <div class="invoice-meta">
            <strong>{{ $sale->invoice_number }}</strong><br>
            Date: {{ $sale->created_at->format('d M Y') }}<br>
            <br>
            @if($sale->payment_status === 'paid')
                <span class="badge badge-paid">Paid</span>
            @elseif($sale->payment_status === 'partial')
                <span class="badge badge-partial">Partial</span>
            @else
                <span class="badge badge-unpaid">Unpaid</span>
            @endif
        </div>
    </div>
</div>

{{-- Parties --}}
<div class="parties">
    <div class="party" style="padding-right: 20px;">
        <h4>From</h4>
        <p><strong>{{ $sale->business->name }}</strong></p>
        <p>{{ $sale->business->phone }}</p>
        <p>{{ $sale->business->email }}</p>
    </div>
    <div class="party" style="padding-left: 20px;">
        <h4>Bill To</h4>
        @if($sale->customer)
            <p><strong>{{ $sale->customer->name }}</strong></p>
            @if($sale->customer->phone)<p>{{ $sale->customer->phone }}</p>@endif
            @if($sale->customer->email)<p>{{ $sale->customer->email }}</p>@endif
        @else
            <p><strong>Walk-in Customer</strong></p>
        @endif
    </div>
</div>

{{-- Items --}}
<table class="items">
    <thead>
        <tr>
            <th style="width:5%">#</th>
            <th style="width:40%">Description</th>
            <th style="width:15%" class="right">Unit Price</th>
            <th style="width:10%" class="right">Qty</th>
            <th style="width:10%" class="right">Disc %</th>
            <th style="width:20%" class="right">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach($sale->items as $i => $item)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $item->product_name }}</td>
            <td class="right">KSh {{ number_format($item->unit_price, 2) }}</td>
            <td class="right">{{ $item->quantity }}</td>
            <td class="right">{{ $item->discount > 0 ? $item->discount . '%' : '—' }}</td>
            <td class="right">KSh {{ number_format($item->subtotal, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- Totals --}}
<div class="totals-wrapper">
    <div class="totals-table">
        <div class="totals-row">
            <div class="totals-label">Subtotal</div>
            <div class="totals-value">KSh {{ number_format($sale->subtotal, 2) }}</div>
        </div>
        @if($sale->discount_amount > 0)
        <div class="totals-row totals-discount">
            <div class="totals-label">Discount</div>
            <div class="totals-value">- KSh {{ number_format($sale->discount_amount, 2) }}</div>
        </div>
        @endif
        <div class="totals-row totals-grand">
            <div class="totals-label">TOTAL</div>
            <div class="totals-value">KSh {{ number_format($sale->total_amount, 2) }}</div>
        </div>
        <div class="totals-row totals-paid">
            <div class="totals-label">Amount Paid</div>
            <div class="totals-value">KSh {{ number_format($sale->paid_amount, 2) }}</div>
        </div>
        @if($sale->balance_due > 0)
        <div class="totals-row totals-balance">
            <div class="totals-label">Balance Due</div>
            <div class="totals-value">KSh {{ number_format($sale->balance_due, 2) }}</div>
        </div>
        @endif
    </div>
</div>

{{-- Payment Info --}}
<div class="payment-info">
    <strong>Payment Method:</strong>
    {{ ucfirst(str_replace('_', ' ', $sale->payment_method)) }}
    @if($sale->notes)
        &nbsp;|&nbsp; <strong>Notes:</strong> {{ $sale->notes }}
    @endif

    @if($sale->payment_method === 'mpesa')
        @php $mpesa = $sale->mpesaTransactions->sortByDesc('created_at')->first(); @endphp
        @if($mpesa)
        <div class="mpesa-box">
            <p class="title">M-Pesa Transaction Details</p>
            <table class="mpesa-detail">
                <tr>
                    <td class="label">Phone Number</td>
                    <td class="value">{{ $mpesa->phone }}</td>
                </tr>
                <tr>
                    <td class="label">Amount</td>
                    <td class="value">KSh {{ number_format($mpesa->amount, 2) }}</td>
                </tr>
                @if($mpesa->mpesa_receipt)
                <tr>
                    <td class="label">Receipt No.</td>
                    <td class="value" style="color:#15803d;">{{ $mpesa->mpesa_receipt }}</td>
                </tr>
                @endif
                <tr>
                    <td class="label">Date &amp; Time</td>
                    <td class="value">{{ $mpesa->created_at->format('d M Y, h:i A') }}</td>
                </tr>
            </table>
        </div>
        @endif
    @endif
</div>

{{-- Payment Terms --}}
@if($sale->business->payment_terms)
<div style="background:#f0fdfa; border-left:4px solid #0f766e; border-radius:4px; padding:10px 15px; margin-bottom:20px; font-size:12px; color:#444;">
    <strong style="color:#1a1a2e; display:block; margin-bottom:4px;">Payment Terms</strong>
    {{ $sale->business->payment_terms }}
</div>
@endif

{{-- Footer --}}
<div class="footer">
    <p>Thank you for your business! For inquiries, contact us at {{ $sale->business->phone }} or {{ $sale->business->email }}.</p>
    <p style="margin-top:4px;">Generated by Merqio POS &mdash; {{ now()->format('d M Y') }}</p>
</div>

</body>
</html>
