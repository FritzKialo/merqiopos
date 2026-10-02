<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt {{ $sale->invoice_number }}</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Courier New', Courier, monospace;
    font-size: 12px;
    color: #000;
    background: #fff;
    max-width: 80mm;
    margin: 0 auto;
    padding: 4mm;
}
.center { text-align: center; }
.right  { text-align: right; }
.bold   { font-weight: bold; }
.large  { font-size: 14px; }
.small  { font-size: 10px; }
.divider { border-top: 1px dashed #000; margin: 4px 0; }
.row    { display: flex; justify-content: space-between; margin-bottom: 2px; }
.row .name { flex: 1; padding-right: 8px; word-break: break-word; }
.row .amount { white-space: nowrap; }
.total-row { font-weight: bold; font-size: 13px; border-top: 1px solid #000; margin-top: 4px; padding-top: 4px; }
@media print {
    body { max-width: 80mm; }
    @page { margin: 2mm; size: 80mm auto; }
}
</style>
</head>
<body>

{{-- Branding accent colour --}}
@php $accentColor = $sale->business->receipt_color ?? '#000000'; @endphp

{{-- Business Header --}}
<div class="center">
    @if($sale->business->show_logo_on_receipt && $sale->business->logo)
    {{-- Fixed square frame (object-fit:contain) rather than a bare
         max-height/max-width image — a wide photo used as a logo used to
         stretch across the whole receipt width and dominate the header;
         this keeps every logo the same tidy size regardless of its own
         aspect ratio, like a small store badge. --}}
    <div style="margin-bottom:8px;">
        <img src="{{ asset('storage/' . $sale->business->logo) }}" alt="{{ $sale->business->name }}"
             style="width:56px;height:56px;object-fit:contain;border:1px solid #ddd;border-radius:6px;padding:3px;">
    </div>
    @endif
    @if($sale->business->receipt_header)
    <div class="small" style="margin-bottom:4px;color:#555;">{{ $sale->business->receipt_header }}</div>
    @endif
    <div class="bold large" style="border-bottom:2px solid {{ $accentColor }};padding-bottom:4px;margin-bottom:4px;">{{ $sale->business->name }}</div>
    @if($sale->business->receipt_tagline)
    <div class="small" style="font-style:italic;margin-bottom:4px;">{{ $sale->business->receipt_tagline }}</div>
    @endif
    @if($sale->business->address)
    <div class="small">{{ $sale->business->address }}{{ $sale->business->city ? ', ' . $sale->business->city : '' }}</div>
    @endif
    @if($sale->business->phone)
    <div class="small">Tel: {{ $sale->business->phone }}</div>
    @endif
    @if($sale->business->kra_pin)
    <div class="small">PIN: {{ $sale->business->kra_pin }}</div>
    @endif
    @if($sale->business->vat_registered && $sale->business->vat_number)
    <div class="small">VAT No: {{ $sale->business->vat_number }}</div>
    @endif
</div>

<div class="divider"></div>

@if(!empty($receiptCopy))
<div class="center bold" style="font-size:15px; letter-spacing:2px; margin-bottom:4px;">{{ $receiptCopy }}</div>
@if($receiptCopy === 'COPY')
<div class="center small" style="margin-bottom:4px;">THIS IS NOT AN OFFICIAL RECEIPT</div>
@endif
@endif

{{-- Sale meta --}}
<div class="row"><span>Receipt #:</span><span class="bold">{{ $sale->invoice_number }}</span></div>
<div class="row"><span>Date:</span><span>{{ $sale->created_at->format('d M Y H:i') }}</span></div>
<div class="row"><span>Cashier:</span><span>{{ $sale->onlineOrder ? 'Online Order' : ($sale->user->name ?? '—') }}</span></div>
@if($sale->customer)
<div class="row"><span>Customer:</span><span>{{ $sale->customer->name }}</span></div>
@elseif($sale->table_guest_name)
<div class="row"><span>Customer:</span><span>{{ $sale->table_guest_name }}</span></div>
@endif
@if($sale->tableOrder?->table)
<div class="row"><span>Table:</span><span>{{ $sale->tableOrder->table->name ?: $sale->tableOrder->table->number }}</span></div>
@endif

<div class="divider"></div>

{{-- Items — a $0-unit_price line only ever exists as a bundle's real
component row (see SaleService/StoreController), expanded purely so stock
decrements correctly per-component; the bundle's own summary line right
above it already shows the real name/price/qty the customer paid for.
Showing both here read as 2-3 confusing, redundant lines per bundle. --}}
@foreach($sale->items as $item)
@continue($item->unit_price == 0)
<div class="row">
    {{-- product_name first (the stored snapshot, always populated — matches
         invoice.blade.php's convention) with product?->name only as a legacy
         fallback; $item->product can be null for a service/retainer line
         with no linked product, and dereferencing it without ?-> would throw
         a PHP warning on every such receipt. --}}
    <span class="name">{{ $item->product_name ?? $item->product?->name ?? '—' }}</span>
    <span class="amount">{{ $item->quantity }} x {{ number_format($item->unit_price, 2) }}</span>
</div>
@if($item->discount > 0)
<div class="row"><span></span><span class="amount">Disc: {{ $item->discount }}%</span></div>
@endif
<div class="row">
    <span></span>
    <span class="amount bold">KSh {{ number_format($item->subtotal, 2) }}@if($sale->business->vat_registered) {{ \App\Models\Sale::taxLabelForItem($item) }}@endif</span>
</div>
@endforeach

<div class="divider"></div>

{{-- Totals --}}
@if($sale->discount_amount > 0 || ($sale->delivery_fee ?? 0) > 0 || $sale->service_charge_amount > 0 || $sale->promoDiscounts()["coupon"] + $sale->promoDiscounts()["loyalty"] > 0)
<div class="row"><span>Subtotal:</span><span>KSh {{ number_format($sale->subtotal, 2) }}</span></div>
@endif
@if($sale->discount_amount > 0)
<div class="row"><span>Discount:</span><span>-KSh {{ number_format($sale->discount_amount, 2) }}</span></div>
@endif
@php $promo = $sale->promoDiscounts(); @endphp
@if($promo['coupon'] > 0)
<div class="row"><span>Coupon:</span><span>-KSh {{ number_format($promo['coupon'], 2) }}</span></div>
@endif
@if($promo['loyalty'] > 0)
<div class="row"><span>Loyalty ({{ number_format($promo['points']) }} pts):</span><span>-KSh {{ number_format($promo['loyalty'], 2) }}</span></div>
@endif
@if(($sale->delivery_fee ?? 0) > 0)
<div class="row"><span>Delivery Fee:</span><span>KSh {{ number_format($sale->delivery_fee, 2) }}</span></div>
@endif
@if($sale->service_charge_amount > 0)
<div class="row"><span>Service charge:</span><span>KSh {{ number_format($sale->service_charge_amount, 2) }}</span></div>
@endif
<div class="row total-row"><span>TOTAL:</span><span>KSh {{ number_format($sale->total_amount, 2) }}</span></div>
@if($sale->business->vat_registered && $sale->tax_amount > 0)
@php $taxRows = $sale->taxBreakdown(); @endphp
<div class="divider"></div>
<div class="small" style="margin-bottom:3px;">PRICES INC. OF VAT WHERE APPLICABLE</div>
<table style="width:100%; font-size:11px; border-collapse:collapse;">
    <tr style="text-align:right;"><th style="text-align:left;">CODE</th><th>RATE</th><th>NET</th><th>TAX</th><th>TOTAL</th></tr>
    @foreach($taxRows as $code => $r)
    <tr style="text-align:right;">
        <td style="text-align:left;">{{ $code }}</td>
        <td>{{ rtrim(rtrim(number_format($r['rate'], 2), '0'), '.') }}%</td>
        <td>{{ number_format($r['net'], 2) }}</td>
        <td>{{ number_format($r['tax'], 2) }}</td>
        <td>{{ number_format($r['total'], 2) }}</td>
    </tr>
    @endforeach
</table>
@endif

{{-- Shown below TOTAL, not above — this is VAT already included in that
     figure (prices are VAT-inclusive), not an amount added on top. Listed
     before as its own line directly above TOTAL, it read like tax added
     on top of the total shown, which didn't match the math. --}}
@if($sale->tax_amount > 0 && !$sale->business->vat_registered)
<div class="row small" style="color:#555;"><span>of which VAT ({{ $sale->business->vat_rate }}%):</span><span>KSh {{ number_format($sale->tax_amount, 2) }}</span></div>
@endif

<div class="divider"></div>

{{-- Payment --}}
<div class="row"><span>Payment:</span><span>{{ ucfirst(str_replace('_', ' ', $sale->payment_method ?? 'cash')) }}</span></div>
@if($sale->payment_method === 'cash')
{{-- A table sale records the sale at the bill amount and keeps what the guest handed over (and any tip) separately. --}}
@php $handed = $sale->amount_tendered ?? $sale->paid_amount; @endphp
<div class="row"><span>Tendered:</span><span>KSh {{ number_format($handed, 2) }}</span></div>
@php $change = $handed - $sale->total_amount - (float) $sale->tip_amount; @endphp
@if($change > 0)
<div class="row bold" style="font-size:13px;"><span>Change:</span><span>KSh {{ number_format($change, 2) }}</span></div>
@endif
@else
<div class="row"><span>Paid:</span><span>KSh {{ number_format($sale->paid_amount, 2) }}</span></div>
@if($sale->mpesa_reference)
<div class="row small"><span>M-Pesa ref:</span><span>{{ $sale->mpesa_reference }}</span></div>
@endif
@endif
@if($sale->tip_amount > 0)
<div class="row"><span>Tip (not part of the sale):</span><span>KSh {{ number_format($sale->tip_amount, 2) }}</span></div>
@endif
@if($sale->balance_due > 0)
<div class="row" style="color:#c00;"><span>Balance Due:</span><span class="bold">KSh {{ number_format($sale->balance_due, 2) }}</span></div>
@endif

{{-- eTIMS compliance --}}
@if(($sale->etims_status ?? null) === 'submitted' && !empty($sale->etims_response['data']['rcptSign']))
@php
    $eData   = $sale->etims_response['data'];
    $eSdc    = $sale->business->etims_sdc_id;
    $eDt     = $eData['sdcDateTime'] ?? null;
    $eStamp  = $eDt ? \Carbon\Carbon::createFromFormat('YmdHis', $eDt) : null;
    $eDash   = fn($s) => trim(chunk_split((string) $s, 4, '-'), '-');
    $eQr     = ($eStamp ? $eStamp->format('dmY') . '#' . $eStamp->format('His') : '#') . '#' . $eSdc . '#' . ($eData['curRcptNo'] ?? '') . '#' . ($eData['intrlData'] ?? '') . '#' . ($eData['rcptSign'] ?? '');
    $eSvg    = null;
    try {
        $eSvg = (new \BaconQrCode\Writer(new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(140, 0),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        )))->writeString($eQr);
    } catch (\Throwable $e) {}
@endphp
<div class="divider"></div>
<div class="center small">
    <div class="bold">SCU Information</div>
    @if($eStamp)<div>Date: {{ $eStamp->format('d/m/Y') }} Time: {{ $eStamp->format('H:i:s') }}</div>@endif
    <div>CU ID: {{ $eSdc }}</div>
    <div>CU Invoice No: {{ $eSdc }}/{{ $eData['curRcptNo'] ?? '' }}</div>
    <div>Receipt counter: {{ $eData['curRcptNo'] ?? '' }}/{{ $eData['totRcptNo'] ?? '' }} NS</div>
    <div>Internal data: {{ $eDash($eData['intrlData'] ?? '') }}</div>
    <div>Receipt signature: {{ $eDash($eData['rcptSign'] ?? '') }}</div>
    @if($eSvg)<div style="margin-top:6px;">{!! $eSvg !!}</div>@endif
</div>
@endif

@if($sale->business->show_shop_qr_on_receipt && $sale->business->store_slug && $sale->business->store_public)
@php
    $shopQrUrl = \App\Support\ShopQr::shopUrl($sale->business, true);
    try { $shopQrSvg = \App\Support\ShopQr::svg($shopQrUrl, 130); } catch (\Throwable $e) { $shopQrSvg = null; }
@endphp
@if($shopQrSvg)
<div class="divider"></div>
<div class="center small">
    <div class="bold">Order online</div>
    <style>.shop-qr svg { width: 100%; height: 100%; display: block; }</style>
    <div class="shop-qr" style="width:26mm;height:26mm;margin:3px auto;overflow:hidden;">{!! $shopQrSvg !!}</div>
    <div>{{ preg_replace('#^https?://#', '', \App\Support\ShopQr::shopUrl($sale->business)) }}</div>
</div>
@endif
@endif

<div class="divider"></div>
<div class="center">
    <div class="bold">Thank you for shopping at {{ $sale->business->name }}!</div>
    @if($sale->business->receipt_footer)
    <div class="small" style="margin-top:4px;color:#555;">{{ $sale->business->receipt_footer }}</div>
    @endif
    <div class="small" style="margin-top:4px;">{{ $sale->created_at->format('d M Y H:i') }}</div>
</div>

@if(!empty($returnUrl))
<div class="noprint-back" style="text-align:center;margin:14px 0;font-family:Arial,sans-serif;">
    <a href="{{ $returnUrl }}" style="display:inline-block;padding:9px 16px;background:#111;color:#fff;text-decoration:none;border-radius:6px;font-size:13px;">← Back to tables</a>
</div>
<style>@media print { .noprint-back { display: none; } }</style>
@endif
<script>
window.onload = function () {
    @if(!empty($returnUrl))
    // Table service: once printed (or cancelled), go straight back to the floor plan.
    window.onafterprint = function () { window.location.href = @json($returnUrl); };
    @endif
    window.print();
};
</script>
</body>
</html>
