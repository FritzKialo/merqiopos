<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $sale->invoice_number }}</title>
    <style>
        /* Standalone page (no shared layout) — Poppins is self-hosted here
           rather than relying on main.css's @font-face, same pattern as
           shop.css. */
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
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
            font-size: 13px;
            color: #1a1a2e;
            background: #f4f6f9;
        }

        /* ── Print bar (hidden when printing) ── */
        .print-bar {
            background: #1a1a2e;
            padding: 12px 24px;
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .print-bar a, .print-bar button {
            padding: 8px 18px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: none;
        }
        .btn-print  { background: #0f766e; color: #fff; }
        .btn-back   { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,.3) !important; }
        @media print { .print-bar { display: none; } body { background: #fff; } }

        /* ── Invoice wrapper ── */
        .inv {
            max-width: 800px;
            margin: 24px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
            overflow: hidden;
        }
        @media print { .inv { margin: 0; box-shadow: none; border-radius: 0; max-width: 100%; } }

        /* ── Header band ── */
        .inv-head {
            background: #1a1a2e;
            color: #fff;
            padding: 28px 32px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .inv-head .biz-name { font-size: 22px; font-weight: 700; margin-bottom: 4px; }
        .inv-head .biz-meta { font-size: 12px; opacity: .75; line-height: 1.7; }
        .inv-head .biz-meta strong { opacity: 1; }
        .inv-title { text-align: right; }
        .inv-title h2 { font-size: 32px; font-weight: 800; letter-spacing: .04em; opacity: .15; }
        .inv-title .inv-num { font-size: 15px; font-weight: 700; margin-top: 4px; }
        .inv-title .inv-date { font-size: 12px; opacity: .7; margin-top: 2px; }
        .inv-title .inv-status {
            display: inline-block;
            margin-top: 8px;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .05em;
        }
        .status-paid    { background: #dcfce7; color: #166534; }
        .status-partial { background: #fef9c3; color: #854d0e; }
        .status-unpaid  { background: #fee2e2; color: #991b1b; }

        /* ── Parties row ── */
        .inv-parties {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 0;
            border-bottom: 1px solid #e8ecf0;
        }
        .inv-party {
            padding: 20px 32px;
            border-right: 1px solid #e8ecf0;
        }
        .inv-party:last-child { border-right: none; }
        .inv-party-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 8px;
        }
        .inv-party p { line-height: 1.6; font-size: 13px; }
        .inv-party strong { font-size: 14px; }

        /* ── Items table ── */
        .inv-table { width: 100%; border-collapse: collapse; }
        .inv-table thead tr { background: #f8fafc; }
        .inv-table th {
            padding: 10px 16px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
            text-align: left;
        }
        .inv-table th.num { text-align: right; }
        .inv-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }
        .inv-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .inv-table tbody tr:last-child td { border-bottom: none; }
        .inv-table .item-name { font-weight: 600; }
        .inv-table .item-meta { font-size: 11px; color: #94a3b8; margin-top: 2px; }
        .inv-table .disc-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 1px 6px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }

        /* ── Totals + VAT split ── */
        .inv-bottom {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 0;
            border-top: 2px solid #e2e8f0;
        }
        .inv-vat-box {
            padding: 20px 24px;
            border-right: 1px solid #e8ecf0;
        }
        .inv-vat-box h4 {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 12px;
        }
        .vat-table { width: 100%; font-size: 12px; border-collapse: collapse; }
        .vat-table th {
            text-align: left;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #94a3b8;
            border-bottom: 1px solid #e8ecf0;
        }
        .vat-table th.r { text-align: right; }
        .vat-table td { padding: 6px 8px; color: #374151; }
        .vat-table td.r { text-align: right; font-variant-numeric: tabular-nums; }

        /* ── Totals box ── */
        .inv-totals { padding: 20px 24px; }
        .tot-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0;
            font-size: 13px;
            color: #374151;
        }
        .tot-row + .tot-row { border-top: 1px solid #f1f5f9; }
        .tot-row.grand {
            background: #1a1a2e;
            color: #fff;
            margin: 8px -24px 0;
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 700;
        }
        .tot-row.paid-row  { color: #16a34a; font-weight: 600; }
        .tot-row.bal-row   { color: #dc2626; font-weight: 700; }
        .tot-row.credit-row { color: #0f766e; font-weight: 600; }
        .tot-label { color: #64748b; }
        .tot-val   { font-weight: 600; font-variant-numeric: tabular-nums; }

        /* ── Payment & notes band ── */
        .inv-meta {
            padding: 16px 32px;
            background: #f8fafc;
            border-top: 1px solid #e8ecf0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            font-size: 12px;
        }
        .inv-meta-item { }
        .inv-meta-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #94a3b8; margin-bottom: 3px; }
        .inv-meta-val   { font-weight: 600; color: #1e293b; }

        /* ── M-Pesa block ── */
        .mpesa-block {
            margin: 0 32px 16px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 14px 18px;
        }
        .mpesa-block h5 { color: #15803d; font-size: 12px; margin-bottom: 8px; }
        .mpesa-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; font-size: 12px; }
        .mpesa-grid .mk { color: #6b7280; }
        .mpesa-grid .mv { font-weight: 600; color: #1e293b; }

        /* ── Loyalty / coupon callout ── */
        .savings-block {
            margin: 0 32px 16px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 12px 18px;
            font-size: 12px;
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }
        .savings-block .si { }
        .savings-block .sk { color: #6b7280; margin-bottom: 2px; }
        .savings-block .sv { font-weight: 700; color: #1d4ed8; font-size: 14px; }

        /* ── Notes / terms ── */
        .inv-notes {
            padding: 16px 32px;
            font-size: 12px;
            color: #374151;
            border-top: 1px solid #e8ecf0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        .inv-notes h5 {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #94a3b8;
            margin-bottom: 6px;
        }
        .inv-notes p { line-height: 1.6; white-space: pre-line; }

        /* ── Footer ── */
        .inv-foot {
            background: #1a1a2e;
            color: rgba(255,255,255,.6);
            text-align: center;
            padding: 14px 32px;
            font-size: 11px;
            line-height: 1.7;
        }
        .inv-foot strong { color: #fff; }

        @media (max-width: 640px) {
            .inv-head { flex-direction: column; gap: 18px; padding: 22px 20px; }
            .inv-title { text-align: left; }
            .inv-title h2 { font-size: 24px; }
            .inv-party { padding: 16px 20px; }
            .inv-parties { grid-template-columns: 1fr; }
            .inv-party { border-right: none; border-bottom: 1px solid #e8ecf0; }
            .inv-bottom { grid-template-columns: 1fr; }
            .inv-vat-box { border-right: none; border-bottom: 1px solid #e8ecf0; }
            .inv-notes { grid-template-columns: 1fr; }
            .mpesa-grid { grid-template-columns: 1fr; }
            .mpesa-block, .savings-block { margin-left: 20px; margin-right: 20px; }
            .inv-meta, .inv-notes { padding-left: 20px; padding-right: 20px; }
            .inv-foot { padding-left: 20px; padding-right: 20px; }

            /* ── Card-stack the two data tables ──────────────────────────
               This is a standalone page (own <head>, no shared layout), so
               it doesn't inherit the app's global table→card-stack rule —
               without this, .inv-table/.vat-table were simply wider than
               the screen with the rightmost column (the actual Amount!)
               rendered off-screen and unreachable, no scrollbar offered. */
            .inv-table, .vat-table { border: none; }
            .inv-table thead, .vat-table thead { display: none; }
            .inv-table tbody tr, .vat-table tbody tr {
                display: block;
                border-bottom: 1px solid #e8ecf0;
                padding: 12px 20px;
            }
            .inv-table tbody tr:last-child, .vat-table tbody tr:last-child { border-bottom: none; }
            .inv-table td, .vat-table td {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 12px;
                padding: 3px 0;
                border: none;
                text-align: right;
            }
            .inv-table td::before, .vat-table td::before {
                content: attr(data-label);
                font-size: 10px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .06em;
                color: #94a3b8;
                text-align: left;
                flex-shrink: 0;
            }
            /* Description is the row's own heading, not a label/value pair —
               give it the full-width, no-label treatment (matches how the
               app's own generic pattern opts these rows out elsewhere). */
            .inv-table td[data-label="Description"] {
                display: block;
                text-align: left;
                padding-bottom: 6px;
            }
            .inv-table td[data-label="Description"]::before { content: none; }
            .inv-table td[data-label="#"] { display: none; }
        }
    </style>
</head>
<body>

{{-- Print bar --}}
<div class="print-bar">
    <button onclick="window.print()" class="btn-print">&#128437; Print Invoice</button>
    <a href="{{ route('sales.show', $sale) }}" class="btn-back">&#8592; Back</a>
</div>

@php
    $business = $sale->business;
    $vatRate  = (float) ($business->vat_rate ?? 16);
    // VAT-inclusive breakdown: tax = total × rate / (100 + rate)
    $vatAmount    = (float) ($sale->vat_amount ?? ($sale->total_amount * $vatRate / (100 + $vatRate)));
    $exclAmount   = (float) $sale->total_amount - $vatAmount;
    $hasDiscount  = ($sale->discount_amount ?? 0) > 0;
    $hasCoupon    = ($sale->coupon_discount_amount ?? 0) > 0;
    $totalSaved   = ($sale->discount_amount ?? 0) + ($sale->coupon_discount_amount ?? 0);
    // Loyalty points redeemed (check LoyaltyTransaction for 'redeem' type on this sale)
    $loyaltyTx    = \App\Models\LoyaltyTransaction::where('sale_id', $sale->id)
                        ->where('type', 'redeem')->first();
@endphp

<div class="inv">

    {{-- ── Header ── --}}
    <div class="inv-head">
        <div>
            @if($business->show_logo_on_invoice && $business->logo)
                {{-- White badge card behind the logo — not a brightness/invert
                     filter (that crushed any non-monochrome logo, e.g. a real
                     photo, into a plain white blob). This reads cleanly on
                     the dark header regardless of the logo's own colours. --}}
                <div style="background:#fff; display:inline-block; padding:8px 10px; border-radius:10px; margin-bottom:10px; line-height:0;">
                    <img src="{{ asset('storage/' . $business->logo) }}"
                         alt="{{ $business->name }}"
                         style="max-height:40px; max-width:130px; display:block;">
                </div>
            @endif
            <div class="biz-name">{{ $business->name }}</div>
            <div class="biz-meta">
                @if($business->address){{ $business->address }}@if($business->city), {{ $business->city }}@endif<br>@endif
                @if($business->phone){{ $business->phone }}<br>@endif
                @if($business->email){{ $business->email }}<br>@endif
                @if($business->kra_pin)<strong>KRA PIN:</strong> {{ $business->kra_pin }}<br>@endif
                @if($business->vat_number)<strong>VAT Reg No:</strong> {{ $business->vat_number }}@endif
            </div>
        </div>
        <div class="inv-title">
            <h2>INVOICE</h2>
            <div class="inv-num">{{ $sale->invoice_number }}</div>
            <div class="inv-date">{{ $sale->created_at->format('d M Y') }} &nbsp;·&nbsp; {{ $sale->created_at->format('g:i A') }}</div>
            @php
                $statusClass = match($sale->payment_status) {
                    'paid'    => 'status-paid',
                    'partial' => 'status-partial',
                    default   => 'status-unpaid',
                };
                $statusLabel = match($sale->payment_status) {
                    'paid'    => '✓ PAID',
                    'partial' => '◑ PARTIAL',
                    default   => '✗ UNPAID',
                };
            @endphp
            <span class="inv-status {{ $statusClass }}">{{ $statusLabel }}</span>
        </div>
    </div>

    {{-- ── Parties ── --}}
    <div class="inv-parties">
        <div class="inv-party">
            <div class="inv-party-label">Bill To</div>
            @if($sale->customer)
                <p><strong>{{ $sale->customer->name }}</strong></p>
                @if($sale->customer->phone)<p>{{ $sale->customer->phone }}</p>@endif
                @if($sale->customer->email)<p>{{ $sale->customer->email }}</p>@endif
                @if($sale->customer->address)<p>{{ $sale->customer->address }}</p>@endif
            @else
                <p><strong>Walk-in Customer</strong></p>
            @endif
        </div>
        <div class="inv-party">
            <div class="inv-party-label">Served By</div>
            @if($sale->onlineOrder)
            <p><strong>Online Order</strong></p>
            <p style="color:#64748b;">Self-checkout</p>
            @else
            <p><strong>{{ $sale->user->name }}</strong></p>
            <p style="color:#64748b;">{{ ucfirst($sale->user->role ?? '') }}</p>
            @endif
        </div>
        <div class="inv-party">
            <div class="inv-party-label">Invoice Details</div>
            <p><strong>No:</strong> {{ $sale->invoice_number }}</p>
            <p><strong>Date:</strong> {{ $sale->created_at->format('d M Y') }}</p>
            <p><strong>Payment:</strong> {{ ucfirst(str_replace('_',' ',$sale->payment_method)) }}</p>
            @if($sale->mpesa_reference)
                <p><strong>Ref:</strong> {{ $sale->mpesa_reference }}</p>
            @endif
        </div>
    </div>

    {{-- ── Line Items ── --}}
    <div class="table-wrapper">
    <table class="inv-table">
        <thead>
            <tr>
                <th style="width:32px;">#</th>
                <th>Description</th>
                <th class="num">Unit Price</th>
                <th class="num">Qty</th>
                <th class="num">Disc</th>
                <th class="num">Amount (VAT Incl.)</th>
            </tr>
        </thead>
        <tbody>
            @php $rowNum = 0; @endphp
            @foreach($sale->items as $item)
            {{-- A $0-unit_price line only ever exists as a bundle's real
            component row, expanded purely so stock decrements correctly
            per-component — the bundle's own summary line already shows the
            real name/price/qty. Numbered separately from the raw loop
            index so hiding these doesn't leave gaps like "1, 3, 4". --}}
            @continue($item->unit_price == 0)
            @php
                $rowNum++;
                $itemVatAmount  = $item->subtotal * $vatRate / (100 + $vatRate);
                $itemExcl       = $item->subtotal - $itemVatAmount;
            @endphp
            <tr>
                <td data-label="#">{{ $rowNum }}</td>
                <td data-label="Description">
                    <div class="item-name">{{ $item->product_name }}</div>
                    @if($item->product?->sku)
                        <div class="item-meta">SKU: {{ $item->product->sku }}</div>
                    @endif
                    @if($item->product?->unit)
                        <div class="item-meta">Unit: {{ $item->product->unit }}</div>
                    @endif
                    <div class="item-meta" style="color:#94a3b8;">
                        Excl. VAT: KSh {{ number_format($itemExcl / max($item->quantity,1), 2) }}/unit
                        &nbsp;·&nbsp; VAT: KSh {{ number_format($itemVatAmount, 2) }}
                    </div>
                </td>
                <td class="num" data-label="Unit Price">KSh {{ number_format($item->unit_price, 2) }}</td>
                <td class="num" data-label="Qty">{{ $item->quantity }}</td>
                <td class="num" data-label="Disc">
                    @if($item->discount > 0)
                        <span class="disc-badge">{{ $item->discount }}%</span>
                    @else
                        —
                    @endif
                </td>
                <td class="num" data-label="Amount"><strong>KSh {{ number_format($item->subtotal, 2) }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>

    {{-- ── VAT breakdown + Totals ── --}}
    <div class="inv-bottom">

        {{-- VAT breakdown (left) --}}
        <div class="inv-vat-box">
            <h4>VAT Summary ({{ $vatRate }}% VAT Inclusive)</h4>
            <div class="table-wrapper">
            <table class="vat-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="r">Excl. VAT</th>
                        <th class="r">VAT ({{ $vatRate }}%)</th>
                        <th class="r">Incl. VAT</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td data-label="Description">Standard Rated ({{ $vatRate }}%)</td>
                        <td class="r" data-label="Excl. VAT">KSh {{ number_format($exclAmount, 2) }}</td>
                        <td class="r" data-label="VAT">KSh {{ number_format($vatAmount, 2) }}</td>
                        <td class="r" data-label="Incl. VAT">KSh {{ number_format($sale->total_amount, 2) }}</td>
                    </tr>
                    <tr style="font-weight:700; border-top:2px solid #e2e8f0;">
                        <td data-label="Description">Total</td>
                        <td class="r" data-label="Excl. VAT">KSh {{ number_format($exclAmount, 2) }}</td>
                        <td class="r" data-label="VAT">KSh {{ number_format($vatAmount, 2) }}</td>
                        <td class="r" data-label="Incl. VAT">KSh {{ number_format($sale->total_amount, 2) }}</td>
                    </tr>
                </tbody>
            </table>
            </div>

            @if($business->kra_pin)
            <p style="margin-top:12px; font-size:11px; color:#94a3b8;">
                All prices are VAT inclusive at {{ $vatRate }}%. KRA PIN: {{ $business->kra_pin }}
                @if($business->vat_number) · VAT Reg: {{ $business->vat_number }}@endif
            </p>
            @endif
        </div>

        {{-- Totals (right) --}}
        <div class="inv-totals">
            <div class="tot-row">
                <span class="tot-label">Subtotal (VAT Incl.)</span>
                <span class="tot-val">KSh {{ number_format($sale->subtotal, 2) }}</span>
            </div>
            @if(($sale->delivery_fee ?? 0) > 0)
            <div class="tot-row">
                <span class="tot-label">Delivery Fee</span>
                <span class="tot-val">KSh {{ number_format($sale->delivery_fee, 2) }}</span>
            </div>
            @endif
            @if($hasDiscount)
            <div class="tot-row" style="color:#dc2626;">
                <span class="tot-label">Discount</span>
                <span class="tot-val">− KSh {{ number_format($sale->discount_amount, 2) }}</span>
            </div>
            @endif
            @if($hasCoupon)
            <div class="tot-row" style="color:#dc2626;">
                <span class="tot-label">Coupon Discount</span>
                <span class="tot-val">− KSh {{ number_format($sale->coupon_discount_amount, 2) }}</span>
            </div>
            @endif
            @if($loyaltyTx)
            <div class="tot-row credit-row">
                <span class="tot-label">Loyalty Points Redeemed</span>
                <span class="tot-val">− KSh {{ number_format(abs($loyaltyTx->points), 2) }}</span>
            </div>
            @endif
            <div class="tot-row grand">
                <span>TOTAL</span>
                <span>KSh {{ number_format($sale->total_amount, 2) }}</span>
            </div>
            <div class="tot-row" style="margin-top:4px; font-size:11px; color:#94a3b8;">
                <span>of which VAT ({{ $vatRate }}%)</span>
                <span>KSh {{ number_format($vatAmount, 2) }}</span>
            </div>
            @if($sale->paid_amount > 0)
            <div class="tot-row paid-row" style="margin-top:8px;">
                <span class="tot-label">Amount Paid</span>
                <span class="tot-val">KSh {{ number_format($sale->paid_amount, 2) }}</span>
            </div>
            @endif
            @if($sale->balance_due > 0)
            <div class="tot-row bal-row">
                <span class="tot-label">Balance Due</span>
                <span class="tot-val">KSh {{ number_format($sale->balance_due, 2) }}</span>
            </div>
            @endif
        </div>
    </div>

    {{-- ── Savings callout ── --}}
    @if($totalSaved > 0 || $loyaltyTx)
    <div class="savings-block">
        @if($totalSaved > 0)
        <div class="si">
            <div class="sk">Total Savings</div>
            <div class="sv">KSh {{ number_format($totalSaved, 2) }}</div>
        </div>
        @endif
        @if($loyaltyTx)
        <div class="si">
            <div class="sk">Loyalty Points Redeemed</div>
            <div class="sv">{{ number_format(abs($loyaltyTx->points), 0) }} pts</div>
        </div>
        @endif
        @if($sale->customer && ($sale->customer->loyalty_points ?? 0) > 0)
        <div class="si">
            <div class="sk">Points Balance After</div>
            <div class="sv">{{ number_format($sale->customer->loyalty_points, 0) }} pts</div>
        </div>
        @endif
    </div>
    @endif

    {{-- ── M-Pesa transaction block ── --}}
    @if($sale->payment_method === 'mpesa' && $sale->mpesaTransactions->isNotEmpty())
    @php $mpesa = $sale->mpesaTransactions->sortByDesc('created_at')->first(); @endphp
    <div class="mpesa-block">
        <h5>&#128242; M-Pesa Transaction Details</h5>
        <div class="mpesa-grid">
            <span class="mk">Phone</span>       <span class="mv">{{ $mpesa->phone }}</span>
            <span class="mk">Amount</span>      <span class="mv">KSh {{ number_format($mpesa->amount, 2) }}</span>
            @if($mpesa->mpesa_receipt)
            <span class="mk">Receipt No.</span> <span class="mv" style="color:#15803d;">{{ $mpesa->mpesa_receipt }}</span>
            @endif
            <span class="mk">Status</span>      <span class="mv">{{ $mpesa->status }}</span>
            <span class="mk">Date</span>        <span class="mv">{{ $mpesa->created_at->format('d M Y, g:i A') }}</span>
        </div>
    </div>
    @endif

    {{-- ── Payment meta strip ── --}}
    <div class="inv-meta">
        <div class="inv-meta-item">
            <div class="inv-meta-label">Payment Method</div>
            <div class="inv-meta-val">{{ ucfirst(str_replace('_',' ',$sale->payment_method)) }}</div>
        </div>
        @if($sale->mpesa_reference)
        <div class="inv-meta-item">
            <div class="inv-meta-label">Reference</div>
            <div class="inv-meta-val">{{ $sale->mpesa_reference }}</div>
        </div>
        @endif
        <div class="inv-meta-item">
            <div class="inv-meta-label">Payment Status</div>
            <div class="inv-meta-val">{{ ucfirst($sale->payment_status) }}</div>
        </div>
        @if($sale->customer && $sale->customer->loyalty_points !== null)
        <div class="inv-meta-item">
            <div class="inv-meta-label">Customer Loyalty Points</div>
            <div class="inv-meta-val">{{ number_format($sale->customer->loyalty_points, 0) }} pts</div>
        </div>
        @endif
    </div>

    {{-- ── Notes & Terms ── --}}
    @if($sale->notes || $business->payment_terms)
    <div class="inv-notes">
        @if($sale->notes)
        <div>
            <h5>Notes</h5>
            <p>{{ $sale->notes }}</p>
        </div>
        @endif
        @if($business->payment_terms)
        <div>
            <h5>Payment Terms</h5>
            <p>{{ $business->payment_terms }}</p>
        </div>
        @endif
    </div>
    @endif

    {{-- ── Footer ── --}}
    <div class="inv-foot">
        <strong>Thank you for your business!</strong><br>
        For inquiries contact {{ $business->phone }}@if($business->email) or {{ $business->email }}@endif.<br>
        <span style="opacity:.5;">Generated by Merqio POS · {{ now()->format('d M Y') }}</span>
    </div>

</div>

</body>
</html>
