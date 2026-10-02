<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shop poster — {{ $business->name }}</title>
@php
    $sizes = [
        'a4'      => ['label' => 'A4 poster',      'page' => '210mm 297mm', 'qr' => '120mm', 'title' => '46pt', 'sub' => '20pt', 'name' => '26pt', 'url' => '17pt'],
        'a5'      => ['label' => 'A5 sign',        'page' => '148mm 210mm', 'qr' => '86mm',  'title' => '34pt', 'sub' => '15pt', 'name' => '20pt', 'url' => '13pt'],
        'sticker' => ['label' => 'Sticker (10 cm)', 'page' => '100mm 100mm', 'qr' => '58mm', 'title' => '18pt', 'sub' => '9pt',  'name' => '11pt', 'url' => '8pt'],
    ];
    $s = $sizes[$size];
@endphp
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, Helvetica, sans-serif; background: #e5e7eb; color: #111; }
.toolbar { background: #111; color: #fff; padding: 10px 16px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; font-size: 14px; }
.toolbar a, .toolbar button { color: #fff; text-decoration: none; padding: 7px 13px; border: 1px solid #555; border-radius: 6px; background: transparent; cursor: pointer; font-size: 14px; }
.toolbar a.on { background: #fff; color: #111; border-color: #fff; }
.sheet { width: {{ explode(' ', $s['page'])[0] }}; min-height: {{ explode(' ', $s['page'])[1] }}; margin: 16px auto; background: #fff; padding: 8mm; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; box-shadow: 0 2px 12px rgba(0,0,0,.15); border-top: 10px solid {{ $business->receipt_color ?: '#111' }}; }
.logo { max-height: 22mm; max-width: 60mm; object-fit: contain; margin-bottom: 5mm; }
.name { font-size: {{ $s['name'] }}; font-weight: 700; margin-bottom: 4mm; }
.title { font-size: {{ $s['title'] }}; font-weight: 800; line-height: 1.1; margin-bottom: 3mm; }
.sub { font-size: {{ $s['sub'] }}; color: #444; margin-bottom: 7mm; }
.qr { width: {{ $s['qr'] }}; height: {{ $s['qr'] }}; padding: 3mm; border: 2px solid #111; border-radius: 4mm; }
.qr svg { width: 100%; height: 100%; display: block; }
.url { margin-top: 6mm; font-size: {{ $s['url'] }}; font-weight: 700; word-break: break-all; }
.contact { margin-top: 3mm; font-size: {{ $s['sub'] }}; color: #555; }
@media print {
    body { background: #fff; }
    .toolbar { display: none; }
    .sheet { margin: 0; box-shadow: none; width: 100%; min-height: 100vh; }
    @page { size: {{ $s['page'] }}; margin: 0; }
}
</style>
</head>
<body>
<div class="toolbar">
    <strong style="margin-right:8px;">Print your shop QR</strong>
    @foreach($sizes as $key => $def)
        <a href="{{ route('settings.store.poster', ['size' => $key]) }}" class="{{ $size === $key ? 'on' : '' }}">{{ $def['label'] }}</a>
    @endforeach
    <button type="button" onclick="window.print()">Print</button>
    <a href="{{ route('settings.store') }}" style="margin-left:auto;">← Back to settings</a>
</div>

<div class="sheet">
    @if($business->logo)
        <img class="logo" src="{{ asset('storage/' . $business->logo) }}" alt="">
    @endif
    <div class="name">{{ $business->name }}</div>
    <div class="title">Order online</div>
    <div class="sub">Scan with your phone camera</div>
    <div class="qr">{!! $qrSvg !!}</div>
    <div class="url">{{ preg_replace('#^https?://#', '', $shopUrl) }}</div>
    @if($business->phone)<div class="contact">{{ $business->phone }}</div>@endif
</div>
</body>
</html>
