<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Statement — {{ $customer->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #1e293b;
            background: #fff;
            padding: 32px;
        }
        /* ── Business Header ── */
        .biz-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            padding-bottom: 20px;
            border-bottom: 2px solid #4f46e5;
            margin-bottom: 24px;
        }
        .biz-logo {
            max-height: 60px;
            max-width: 160px;
        }
        .biz-info h1 {
            font-size: 20px;
            color: #4f46e5;
            margin-bottom: 4px;
        }
        .biz-info p { color: #64748b; font-size: 11px; line-height: 1.5; }
        .doc-title {
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: #1e293b;
            text-transform: uppercase;
            margin-bottom: 20px;
        }
        /* ── Customer + Period block ── */
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .info-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding: 12px 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .info-col:first-child { border-right: none; border-radius: 4px 0 0 4px; }
        .info-col:last-child  { border-radius: 0 4px 4px 0; }
        .info-col h3 {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
            margin-bottom: 8px;
        }
        .info-col p { margin-bottom: 3px; line-height: 1.5; }
        /* ── Summary boxes ── */
        .summary-row {
            display: table;
            width: 100%;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        .summary-cell {
            display: table-cell;
            width: 33.33%;
            text-align: center;
            padding: 12px 8px;
            border-right: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .summary-cell:last-child { border-right: none; }
        .summary-cell .val {
            font-size: 15px;
            font-weight: 700;
            color: #4f46e5;
            line-height: 1.2;
        }
        .summary-cell .val.outstanding { color: #dc2626; font-size: 17px; }
        .summary-cell .val.paid        { color: #16a34a; }
        .summary-cell .lbl {
            font-size: 10px;
            color: #64748b;
            margin-top: 3px;
        }
        /* ── Transactions table ── */
        table.txns {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.txns th {
            background: #4f46e5;
            color: white;
            padding: 8px 10px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
        }
        table.txns td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
        }
        table.txns tr.sale-row    td { }
        table.txns tr.payment-row td { color: #16a34a; }
        table.txns tfoot td {
            font-weight: 700;
            background: #f8fafc;
            padding: 10px;
            font-size: 12px;
        }
        .balance-positive { color: #dc2626; font-weight: 700; }
        .balance-zero     { color: #16a34a; font-weight: 700; }
        /* ── Footer ── */
        .pdf-footer {
            border-top: 1px solid #e2e8f0;
            padding-top: 12px;
            text-align: center;
            font-size: 10px;
            color: #94a3b8;
            margin-top: 32px;
        }
    </style>
</head>
<body>

{{-- Business Header --}}
<div class="biz-header">
    <div class="biz-info">
        @if($customer->business->logo)
            <img src="{{ public_path('storage/' . $customer->business->logo) }}"
                 class="biz-logo" alt="{{ $customer->business->name }}">
        @endif
        <h1>{{ $customer->business->name }}</h1>
        @if($customer->business->address)
            <p>{{ $customer->business->address }}@if($customer->business->city), {{ $customer->business->city }}@endif</p>
        @endif
        @if($customer->business->phone)
            <p>Tel: {{ $customer->business->phone }}</p>
        @endif
        @if($customer->business->email)
            <p>Email: {{ $customer->business->email }}</p>
        @endif
    </div>
    <div style="text-align: right; color: #64748b; font-size: 11px;">
        <p>Date: {{ now()->format('d M Y') }}</p>
        @if($customer->business->kra_pin)
            <p>KRA PIN: {{ $customer->business->kra_pin }}</p>
        @endif
    </div>
</div>

{{-- Document Title --}}
<div class="doc-title">Account Statement</div>

{{-- Customer Info + Period --}}
<div class="info-grid">
    <div class="info-col">
        <h3>Customer Details</h3>
        <p><strong>{{ $customer->name }}</strong></p>
        @if($customer->phone)<p>Tel: {{ $customer->phone }}</p>@endif
        @if($customer->email)<p>Email: {{ $customer->email }}</p>@endif
        @if($customer->address)<p>{{ $customer->address }}</p>@endif
    </div>
    <div class="info-col">
        <h3>Statement Period</h3>
        <p><strong>From:</strong> {{ \Carbon\Carbon::parse($fromDate)->format('d M Y') }}</p>
        <p><strong>To:</strong> {{ \Carbon\Carbon::parse($toDate)->format('d M Y') }}</p>
    </div>
</div>

{{-- Summary --}}
<div class="summary-row">
    <div class="summary-cell">
        <div class="val">KES {{ number_format($summary['total_sales'], 2) }}</div>
        <div class="lbl">Total Purchases</div>
    </div>
    <div class="summary-cell">
        <div class="val paid">KES {{ number_format($summary['total_paid'], 2) }}</div>
        <div class="lbl">Total Paid</div>
    </div>
    <div class="summary-cell">
        <div class="val outstanding">KES {{ number_format($summary['outstanding'], 2) }}</div>
        <div class="lbl">Outstanding Balance</div>
    </div>
</div>

{{-- Transactions --}}
@if(empty($transactions))
    <p style="text-align: center; color: #94a3b8; padding: 24px 0;">
        No transactions found for this period.
    </p>
@else
    <table class="txns">
        <thead>
            <tr>
                <th>Date</th>
                <th>Reference</th>
                <th>Type</th>
                <th style="text-align: right;">Amount (KES)</th>
                <th style="text-align: right;">Running Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $tx)
            <tr class="{{ $tx['type'] === 'payment' ? 'payment-row' : 'sale-row' }}">
                <td style="white-space: nowrap;">
                    {{ \Carbon\Carbon::parse($tx['date'])->format('d M Y') }}
                </td>
                <td>{{ $tx['reference'] }}</td>
                <td>{{ $tx['type'] === 'sale' ? 'Sale' : 'Payment' }}</td>
                <td style="text-align: right;">
                    @if($tx['amount'] > 0)
                        + {{ number_format($tx['amount'], 2) }}
                    @else
                        - {{ number_format(abs($tx['amount']), 2) }}
                    @endif
                </td>
                <td style="text-align: right;"
                    class="{{ $tx['balance'] > 0 ? 'balance-positive' : 'balance-zero' }}">
                    {{ number_format($tx['balance'], 2) }}
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align: right;">Outstanding Balance</td>
                <td colspan="2" style="text-align: right; color: {{ $summary['outstanding'] > 0 ? '#dc2626' : '#16a34a' }};">
                    KES {{ number_format($summary['outstanding'], 2) }}
                </td>
            </tr>
        </tfoot>
    </table>
@endif

{{-- Footer --}}
<div class="pdf-footer">
    <p>Generated by Merqio POS on {{ now()->format('d M Y, g:i A') }}</p>
    <p>{{ $customer->business->name }} &mdash; Confidential Account Statement</p>
</div>

</body>
</html>
