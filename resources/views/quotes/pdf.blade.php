<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quote {{ $quote->quote_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 13px;
            color: #1a1a2e;
            background: #ffffff;
            padding: 24px;
        }

        /* ── Header ── */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 22px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 16px;
        }
        .header-left  { display: table-cell; width: 60%; vertical-align: top; }
        .header-right { display: table-cell; width: 40%; vertical-align: top; text-align: right; }

        .business-name { font-size: 22px; font-weight: bold; color: #0f766e; margin-bottom: 4px; }
        .business-detail { color: #555; font-size: 12px; line-height: 1.6; }

        .doc-title { font-size: 28px; font-weight: bold; color: #0f766e; letter-spacing: 2px; margin-bottom: 8px; }
        .doc-meta { font-size: 12px; color: #555; line-height: 1.8; }
        .doc-meta strong { color: #1a1a2e; }

        .badge {
            display: inline-block; padding: 3px 10px; border-radius: 12px;
            font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .badge-draft     { background: #f1f5f9; color: #475569; }
        .badge-sent      { background: #e0f2fe; color: #075985; }
        .badge-accepted  { background: #d1fae5; color: #065f46; }
        .badge-rejected  { background: #fee2e2; color: #991b1b; }
        .badge-expired   { background: #fef3c7; color: #92400e; }
        .badge-converted { background: #ede9fe; color: #5b21b6; }

        /* ── Parties ── */
        .parties { display: table; width: 100%; margin-bottom: 18px; }
        .party { display: table-cell; width: 50%; vertical-align: top; }
        .party h4 {
            font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #0f766e;
            margin-bottom: 6px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px;
        }
        .party p { font-size: 12px; color: #444; line-height: 1.7; }
        .party p strong { color: #1a1a2e; font-size: 13px; }

        /* ── Items table ── */
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.items thead tr { background: #0f766e; color: white; }
        table.items thead th {
            padding: 9px 10px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; text-align: left;
        }
        table.items thead th.right { text-align: right; }
        table.items tbody tr { border-bottom: 1px solid #f3f4f6; }
        table.items tbody td { padding: 9px 10px; font-size: 12px; color: #333; vertical-align: top; }
        table.items tbody td.right { text-align: right; }

        /* ── Totals ── */
        .totals-wrapper { text-align: right; margin-bottom: 18px; }
        .totals-table { display: inline-table; min-width: 260px; }
        .totals-row { display: table-row; }
        .totals-label, .totals-value { display: table-cell; padding: 5px 10px; font-size: 13px; }
        .totals-label { color: #555; text-align: left; }
        .totals-value { text-align: right; font-weight: 600; color: #1a1a2e; }
        .totals-grand .totals-label, .totals-grand .totals-value {
            font-size: 15px; font-weight: bold; color: #0f766e; padding-top: 8px; border-top: 2px solid #0f766e;
        }
        .totals-discount .totals-value { color: #dc2626; }

        /* ── Notes / terms ── */
        .note-box {
            background: #f0fdfa; border-left: 4px solid #0f766e; border-radius: 4px;
            padding: 12px 15px; margin-bottom: 18px; font-size: 12px; color: #444;
        }
        .note-box strong { color: #1a1a2e; display: block; margin-bottom: 4px; }

        /* ── Signature ── */
        .signature-row { display: table; width: 100%; margin-top: 28px; }
        .signature-cell { display: table-cell; width: 50%; vertical-align: top; }
        .signature-line {
            border-top: 1px solid #1a1a2e; width: 200px; margin-top: 26px; padding-top: 6px;
            font-size: 11px; color: #555;
        }

        /* ── Footer ── */
        .footer {
            border-top: 1px solid #e5e7eb; padding-top: 12px; margin-top: 18px;
            text-align: center; font-size: 11px; color: #888; line-height: 1.6;
        }
    </style>
</head>
<body>

{{-- Header --}}
<div class="header">
    <div class="header-left">
        @if($quote->business->logo)
            <img src="{{ public_path('storage/' . $quote->business->logo) }}"
                 alt="{{ $quote->business->name }}"
                 style="max-height:60px; max-width:180px; margin-bottom:8px; display:block;">
        @endif
        <div class="business-name">{{ $quote->business->name }}</div>
        <div class="business-detail">
            {{ $quote->business->phone }}<br>
            {{ $quote->business->email }}<br>
            @if($quote->business->address)
                {{ $quote->business->address }}{{ $quote->business->city ? ', ' . $quote->business->city : '' }}<br>
            @endif
            @if($quote->business->kra_pin)
                KRA PIN: {{ $quote->business->kra_pin }}
            @endif
        </div>
    </div>
    <div class="header-right">
        <div class="doc-title">QUOTATION</div>
        <div class="doc-meta">
            <strong>{{ $quote->quote_number }}</strong><br>
            Date: {{ $quote->quote_date->format('d M Y') }}<br>
            @if($quote->valid_until)
                Valid Until: {{ $quote->valid_until->format('d M Y') }}<br>
            @endif
            <br>
            <span class="badge badge-{{ $quote->status }}">{{ ucfirst($quote->status) }}</span>
        </div>
    </div>
</div>

{{-- Parties --}}
<div class="parties">
    <div class="party" style="padding-right: 20px;">
        <h4>From</h4>
        <p><strong>{{ $quote->business->name }}</strong></p>
        <p>{{ $quote->business->phone }}</p>
        <p>{{ $quote->business->email }}</p>
    </div>
    <div class="party" style="padding-left: 20px;">
        <h4>Quote For</h4>
        @if($quote->customer)
            <p><strong>{{ $quote->customer->name }}</strong></p>
            @if($quote->customer->phone)<p>{{ $quote->customer->phone }}</p>@endif
            @if($quote->customer->email)<p>{{ $quote->customer->email }}</p>@endif
        @else
            <p><strong>—</strong></p>
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
            <th style="width:30%" class="right">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach($quote->items as $i => $item)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>
                {{ $item->product_name }}
                @if($item->description)
                    <br><span style="color:#888;font-size:11px;">{{ $item->description }}</span>
                @endif
            </td>
            <td class="right">KSh {{ number_format($item->unit_price, 2) }}</td>
            <td class="right">{{ $item->quantity }}</td>
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
            <div class="totals-value">KSh {{ number_format($quote->subtotal, 2) }}</div>
        </div>
        @if($quote->discount_amount > 0)
        <div class="totals-row totals-discount">
            <div class="totals-label">Discount</div>
            <div class="totals-value">- KSh {{ number_format($quote->discount_amount, 2) }}</div>
        </div>
        @endif
        @if($quote->tax_amount > 0)
        <div class="totals-row">
            <div class="totals-label">Tax ({{ rtrim(rtrim(number_format($quote->tax_rate, 2), '0'), '.') }}%)</div>
            <div class="totals-value">KSh {{ number_format($quote->tax_amount, 2) }}</div>
        </div>
        @endif
        <div class="totals-row totals-grand">
            <div class="totals-label">TOTAL</div>
            <div class="totals-value">KSh {{ number_format($quote->total, 2) }}</div>
        </div>
    </div>
</div>

{{-- Terms --}}
@if($quote->terms)
<div class="note-box">
    <strong>Terms & Conditions</strong>
    {{ $quote->terms }}
</div>
@endif

{{-- Notes --}}
@if($quote->notes)
<div class="note-box">
    <strong>Notes</strong>
    {{ $quote->notes }}
</div>
@endif

{{-- Signature --}}
<div class="signature-row">
    <div class="signature-cell">
        <div class="signature-line">Prepared By — {{ $quote->user->name ?? '—' }}</div>
    </div>
    <div class="signature-cell">
        <div class="signature-line">Authorized Signatory</div>
    </div>
</div>

{{-- Footer --}}
<div class="footer">
    @if($quote->valid_until)
        <p>This quotation is valid until <strong>{{ $quote->valid_until->format('d M Y') }}</strong>.</p>
    @endif
    <p>For inquiries, contact us at {{ $quote->business->phone }} or {{ $quote->business->email }}.</p>
    <p style="margin-top:4px;">Generated by Merqio POS &mdash; {{ now()->format('d M Y') }}</p>
</div>

</body>
</html>
