<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $creditNote->number }}</title>
    <style>
        /* Standalone page (no shared layout) — Poppins self-hosted here
           rather than relying on main.css's @font-face. */
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
        body { font-family: 'Poppins', 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 13px; color: #1a1a2e; background: #fff; padding: 40px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; border-bottom: 1px solid #e5e7eb; padding-bottom: 22px; }
        .business-name { font-size: 20px; font-weight: 700; color: #0f766e; margin-bottom: 4px; }
        .doc-title { font-size: 24px; font-weight: 700; text-align: right; color: #b91c1c; letter-spacing: 1.5px; }
        .doc-number { font-size: 13px; color: #888; text-align: right; margin-top: 4px; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 22px; }
        .meta-block { font-size: 12px; line-height: 1.7; }
        .meta-block strong { display: block; font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.06em; color: #0f766e; margin-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        thead tr { background: #b91c1c; color: #fff; }
        th { padding: 9px 12px; text-align: left; font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; }
        td { padding: 9px 12px; border-bottom: 1px solid #f3f4f6; font-size: 12.5px; }
        .text-right { text-align: right; }
        .totals-row td { font-weight: 700; border-top: 2px solid #b91c1c; color: #b91c1c; font-size: 14px; }
        .reason-block { background: #fef2f2; border-left: 4px solid #b91c1c; border-radius: 4px; padding: 12px 15px; margin-bottom: 22px; font-size: 12px; color: #444; }
        .footer { margin-top: 36px; text-align: center; font-size: 11px; color: #888; border-top: 1px solid #eee; padding-top: 14px; }
        @media print { body { padding: 20px; } }
        @media screen and (max-width: 600px) {
            body { padding: 16px; }
            .header, .meta { flex-direction: column; }
            .header > div:last-child, .meta-block:last-child { text-align: left !important; margin-top: 14px; }
            .doc-title, .doc-number { text-align: left; }
            .table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            table { min-width: 480px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            @if($business->show_logo_on_invoice && $business->logo)
                <img src="{{ asset('storage/' . $business->logo) }}" alt="{{ $business->name }}" style="max-height:50px;max-width:160px;margin-bottom:8px;display:block;">
            @endif
            <div class="business-name">{{ $business->name }}</div>
            @if($business->address)<div>{{ $business->address }}</div>@endif
            @if($business->phone)<div>{{ $business->phone }}</div>@endif
            @if($business->email)<div>{{ $business->email }}</div>@endif
            @if($business->kra_pin)<div>KRA PIN: {{ $business->kra_pin }}</div>@endif
            @if($business->vat_number)<div>VAT No: {{ $business->vat_number }}</div>@endif
        </div>
        <div>
            <div class="doc-title">CREDIT NOTE</div>
            <div class="doc-number">{{ $creditNote->number }}</div>
            <div class="doc-number">Date: {{ ($creditNote->issued_at ?? $creditNote->created_at)->format('d M Y') }}</div>
            @if($creditNote->invoice)
            <div class="doc-number">Invoice Ref: {{ $creditNote->invoice->invoice_number }}</div>
            @endif
        </div>
    </div>

    <div class="meta">
        <div class="meta-block">
            <strong>Credit To</strong>
            {{ $creditNote->customer?->name ?? 'Walk-in Customer' }}<br>
            @if($creditNote->customer?->phone){{ $creditNote->customer->phone }}<br>@endif
            @if($creditNote->customer?->email){{ $creditNote->customer->email }}@endif
        </div>
        <div class="meta-block" style="text-align:right;">
            <strong>Status</strong>
            {{ ucfirst($creditNote->status) }}
        </div>
    </div>

    <div class="reason-block">
        <strong style="font-size:11px;text-transform:uppercase;letter-spacing:0.04em;">Reason</strong><br>
        {{ $creditNote->reason }}
    </div>

    <div class="table-scroll">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Description</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">VAT</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($creditNote->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->description }}</td>
                <td class="text-right">{{ number_format($item->quantity, 2) }}</td>
                <td class="text-right">KSh {{ number_format($item->unit_price, 2) }}</td>
                <td class="text-right">{{ $item->vat_rate > 0 ? $item->vat_rate.'%' : '—' }}</td>
                <td class="text-right">KSh {{ number_format($item->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" class="text-right" style="color:#555;">Subtotal</td>
                <td class="text-right">KSh {{ number_format($creditNote->subtotal, 2) }}</td>
            </tr>
            @if($creditNote->vat_amount > 0)
            <tr>
                <td colspan="5" class="text-right" style="color:#555;">VAT</td>
                <td class="text-right">KSh {{ number_format($creditNote->vat_amount, 2) }}</td>
            </tr>
            @endif
            <tr class="totals-row">
                <td colspan="5" class="text-right">TOTAL CREDIT</td>
                <td class="text-right">KSh {{ number_format($creditNote->total, 2) }}</td>
            </tr>
        </tfoot>
    </table>
    </div>

    <div class="footer">
        {{ $business->name }} &mdash; {{ $creditNote->number }} &mdash; This is a credit note, not a tax invoice
    </div>

    <script>window.onload = function() { window.print(); }</script>
</body>
</html>
