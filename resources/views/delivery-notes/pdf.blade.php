<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $deliveryNote->number }}</title>
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
        .doc-title { font-size: 24px; font-weight: 700; text-align: right; color: #0f766e; letter-spacing: 1.5px; }
        .doc-number { font-size: 13px; color: #888; text-align: right; margin-top: 4px; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 22px; }
        .meta-block { font-size: 12px; line-height: 1.7; }
        .meta-block strong { display: block; font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.06em; color: #0f766e; margin-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        thead tr { background: #0f766e; color: #fff; }
        th { padding: 9px 12px; text-align: left; font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; }
        td { padding: 9px 12px; border-bottom: 1px solid #f3f4f6; font-size: 12.5px; }
        .text-right { text-align: right; }
        .signatures { display: flex; justify-content: space-between; margin-top: 50px; }
        .sig-block { text-align: center; }
        .sig-line { border-top: 1px solid #ccc; width: 200px; margin: 0 auto 6px; padding-top: 6px; font-size: 11px; color: #888; }
        .footer { margin-top: 36px; text-align: center; font-size: 11px; color: #888; border-top: 1px solid #eee; padding-top: 14px; }
        @media print { body { padding: 20px; } }
        @media screen and (max-width: 600px) {
            body { padding: 16px; }
            .header, .meta { flex-direction: column; }
            .header > div:last-child, .meta-block:last-child { text-align: left !important; margin-top: 14px; }
            .doc-title, .doc-number { text-align: left; }
            .signatures { flex-direction: column; gap: 20px; }
            .sig-line { margin: 0 0 6px; }
            .table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            table { min-width: 420px; }
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
        </div>
        <div>
            <div class="doc-title">DELIVERY NOTE</div>
            <div class="doc-number">{{ $deliveryNote->number }}</div>
            <div class="doc-number">Date: {{ $deliveryNote->created_at->format('d M Y') }}</div>
            @if($deliveryNote->dispatched_at)
            <div class="doc-number">Dispatched: {{ $deliveryNote->dispatched_at->format('d M Y') }}</div>
            @endif
        </div>
    </div>

    <div class="meta">
        <div class="meta-block">
            <strong>Deliver To</strong>
            {{ $deliveryNote->customer?->name ?? 'Walk-in Customer' }}<br>
            @if($deliveryNote->customer?->phone){{ $deliveryNote->customer->phone }}<br>@endif
            @if($deliveryNote->delivery_address){{ $deliveryNote->delivery_address }}@endif
        </div>
        <div class="meta-block" style="text-align:right;">
            @if($deliveryNote->invoice)
            <strong>Invoice Ref</strong>
            {{ $deliveryNote->invoice->invoice_number }}<br>
            @endif
            <strong>Status</strong>
            {{ ucfirst($deliveryNote->status) }}
        </div>
    </div>

    <div class="table-scroll">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Description</th>
                <th class="text-right">Quantity</th>
                <th>Unit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($deliveryNote->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->description }}</td>
                <td class="text-right">{{ number_format($item->quantity, 2) }}</td>
                <td>{{ $item->unit ?? '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>

    @if($deliveryNote->notes)
    <div style="margin-bottom:25px;font-size:12px;">
        <strong style="font-size:11px;text-transform:uppercase;letter-spacing:0.04em;">Notes</strong><br>
        {{ $deliveryNote->notes }}
    </div>
    @endif

    <div class="signatures">
        <div class="sig-block">
            <div style="height:50px;"></div>
            <div class="sig-line">Dispatched By</div>
        </div>
        <div class="sig-block">
            <div style="height:50px;"></div>
            <div class="sig-line">Received By</div>
        </div>
        <div class="sig-block">
            <div style="height:50px;"></div>
            <div class="sig-line">Date Received</div>
        </div>
    </div>

    <div class="footer">
        {{ $business->name }} &mdash; {{ $deliveryNote->number }} &mdash; Generated {{ now()->format('d M Y') }}
    </div>

    <script>window.onload = function() { window.print(); }</script>
</body>
</html>
