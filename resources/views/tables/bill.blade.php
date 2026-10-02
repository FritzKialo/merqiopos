<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bill — Table {{ $order->table->name ?: $order->table->number }}</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Courier New', Courier, monospace; font-size: 12px; color: #000; background: #fff; max-width: 80mm; margin: 0 auto; padding: 4mm; }
.center { text-align: center; }
.bold   { font-weight: bold; }
.small  { font-size: 10px; }
.divider { border-top: 1px dashed #000; margin: 4px 0; }
.row { display: flex; justify-content: space-between; margin-bottom: 2px; }
.row .name { flex: 1; padding-right: 8px; word-break: break-word; }
.row .amount { white-space: nowrap; }
.total-row { font-weight: bold; font-size: 14px; border-top: 1px solid #000; margin-top: 4px; padding-top: 4px; }
.stamp { border: 2px solid #000; padding: 3px 0; font-weight: bold; letter-spacing: 1px; margin: 4px 0; }
.noprint { text-align: center; margin: 12px 0; font-family: Arial, sans-serif; }
.noprint a { display: inline-block; margin: 4px; padding: 8px 14px; background: #111; color: #fff; text-decoration: none; border-radius: 6px; font-size: 13px; }
@media print { body { max-width: 80mm; } @page { margin: 2mm; size: 80mm auto; } .noprint { display: none; } }
</style>
</head>
<body>

<div class="center">
    <div class="bold" style="font-size:15px;">{{ $order->business->name }}</div>
    @if($order->business->address)
    <div class="small">{{ $order->business->address }}{{ $order->business->city ? ', ' . $order->business->city : '' }}</div>
    @endif
    @if($order->business->phone)
    <div class="small">Tel: {{ $order->business->phone }}</div>
    @endif
</div>

<div class="divider"></div>
<div class="center stamp">BILL — NOT A RECEIPT</div>

<div class="row"><span>Table:</span><span class="bold">{{ $order->table->name ?: $order->table->number }}</span></div>
<div class="row"><span>Order #:</span><span>{{ $order->id }}</span></div>
<div class="row"><span>Date:</span><span>{{ ($order->billed_at ?? now())->format('d M Y H:i') }}</span></div>
<div class="row"><span>Served by:</span><span>{{ $order->user->name ?? '—' }}</span></div>
@if($order->customer_name)
<div class="row"><span>Guest:</span><span>{{ $order->customer_name }}</span></div>
@endif

<div class="divider"></div>

@foreach($order->items as $item)
<div class="row">
    <span class="name">{{ $item->product_name }}</span>
    <span class="amount">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }} x {{ number_format($item->unit_price, 2) }}</span>
</div>
<div class="row"><span></span><span class="amount">{{ number_format($item->total, 2) }}</span></div>
@endforeach

@if($order->service_charge_amount > 0)
<div class="row" style="margin-top:4px;"><span>Subtotal:</span><span>KSh {{ number_format($order->subtotal, 2) }}</span></div>
<div class="row"><span>Service charge ({{ rtrim(rtrim(number_format($order->service_charge_percent, 2), '0'), '.') }}%):</span><span>KSh {{ number_format($order->service_charge_amount, 2) }}</span></div>
@endif
<div class="row total-row"><span>TOTAL DUE:</span><span>KSh {{ number_format($order->total, 2) }}</span></div>
@if($order->business->vat_registered)
<div class="small" style="margin-top:3px;">Prices include VAT where applicable.</div>
@endif

<div class="divider"></div>
<div class="center">
    <div class="bold">Please pay at the till</div>
    <div class="small" style="margin-top:3px;">Your receipt is issued when you pay.</div>
    <div class="small" style="margin-top:3px;">Thank you for dining with us!</div>
</div>

<div class="noprint">
    <a href="{{ route('tables.orders.show', $order) }}">← Back to order</a>
    <a href="#" onclick="window.print(); return false;">Print again</a>
</div>

<script>
window.onload = function () {
    window.onafterprint = function () { window.location.href = @json(route('tables.orders.show', $order)); };
    window.print();
};
</script>
</body>
</html>
