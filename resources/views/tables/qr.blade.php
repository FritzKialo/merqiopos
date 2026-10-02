@extends('layouts.app')
@section('title', 'QR order code — Table ' . ($table->name ?: $table->number))
@section('content')
<div class="page-header">
    <h1>QR order code — Table {{ $table->name ?: $table->number }}</h1>
    <a href="{{ route('tables.floor') }}" class="btn btn-secondary">← Floor Plan</a>
</div>

<div class="card" style="max-width:420px;">
    <div class="card-body" style="text-align:center;">
        <p style="font-size:.88rem;color:var(--color-text-muted);margin-bottom:16px;">
            A customer scans this to browse the menu and send an order request from their own phone —
            a member of staff still has to approve it before it reaches the kitchen or the bill.
        </p>
        <div id="tableQrBox" style="width:240px;height:240px;margin:0 auto 16px;border:2px solid #111;border-radius:12px;padding:10px;">
            {!! $svg !!}
        </div>
        <div style="font-size:.8rem;word-break:break-all;color:var(--color-text-muted);margin-bottom:16px;">{{ $url }}</div>
        <button type="button" class="btn btn-primary" onclick="window.print()">Print this page</button>
        <a href="{{ route('tables.orders.show', $table->currentOrder ?? 0) }}" class="btn btn-secondary" style="{{ $table->currentOrder ? '' : 'display:none;' }}">Back to order</a>
    </div>
</div>

<style>
#tableQrBox svg { width: 100%; height: 100%; display: block; }
@media print {
    .app-topbar, .app-sidebar, .page-header a, .card .btn { display: none !important; }
    .card { border: none; box-shadow: none; }
}
</style>
@endsection
