<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kitchen — Table {{ $order->table->name ?: $order->table->number }}</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Courier New', Courier, monospace; font-size: 14px; color: #000; background: #fff; max-width: 80mm; margin: 0 auto; padding: 4mm; }
.center { text-align: center; }
.bold   { font-weight: bold; }
.divider { border-top: 2px dashed #000; margin: 6px 0; }
.title { font-size: 18px; font-weight: bold; letter-spacing: 1px; }
.tbl { font-size: 26px; font-weight: bold; margin: 4px 0; }
.item { display: flex; gap: 10px; margin: 8px 0 2px; font-size: 18px; font-weight: bold; }
.item .qty { min-width: 42px; }
.note { font-size: 14px; margin-left: 52px; font-weight: bold; }
.meta { font-size: 12px; }
.noprint { text-align: center; margin: 12px 0; font-family: Arial, sans-serif; }
.noprint a { display: inline-block; margin: 4px; padding: 8px 14px; background: #111; color: #fff; text-decoration: none; border-radius: 6px; font-size: 13px; }
@media print { @page { margin: 2mm; size: 80mm auto; } .noprint { display: none; } }
</style>
</head>
<body>

<div class="center">
    <div class="title">KITCHEN ORDER</div>
    <div class="tbl">TABLE {{ $order->table->name ?: $order->table->number }}</div>
    @if($reprint)<div class="bold">*** REPRINT — ALL ITEMS ***</div>@endif
</div>

<div class="divider"></div>
<div class="meta">Order #{{ $order->id }} &nbsp; {{ now()->format('d M H:i') }}</div>
<div class="meta">Server: {{ $order->user->name ?? '—' }}</div>
<div class="divider"></div>

@forelse($items as $item)
<div class="item">
    <span class="qty">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }} x</span>
    <span>{{ $item->product_name }}</span>
</div>
@if($item->notes)
<div class="note">&raquo; {{ $item->notes }}</div>
@endif
@empty
<div class="center">No items.</div>
@endforelse

<div class="divider"></div>

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
