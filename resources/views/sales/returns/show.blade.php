@extends('layouts.app')
@section('title', $saleReturn->return_number)
@push('styles')
<style>
@media (max-width: 700px) {
    .return-show-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $saleReturn->return_number }}</h1>
            <p class="page-subtitle">Return details</p>
        </div>
        <a href="{{ route('returns.index') }}" class="btn btn--outline">Back</a>
    </div>

    <div class="return-show-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
        <div class="table-card">
            <div class="card-body">
                <h3 style="margin-bottom:1rem;">Return Info</h3>
                <table class="table-plain" style="width:100%;border-collapse:collapse;">
                    <tr><td style="padding:0.4rem 0;color:var(--text-muted);">Return #</td><td><strong>{{ $saleReturn->return_number }}</strong></td></tr>
                    <tr><td style="padding:0.4rem 0;color:var(--text-muted);">Original Sale</td><td><a href="{{ route('sales.show', $saleReturn->sale) }}">{{ $saleReturn->sale?->invoice_number }}</a></td></tr>
                    <tr><td style="padding:0.4rem 0;color:var(--text-muted);">Customer</td><td>{{ $saleReturn->customer?->name ?? 'Walk-in' }}</td></tr>
                    <tr><td style="padding:0.4rem 0;color:var(--text-muted);">Refund Method</td><td>{{ ucwords(str_replace("_", " ", $saleReturn->refund_method)) }}</td></tr>
                    <tr><td style="padding:0.4rem 0;color:var(--text-muted);">Stock Action</td><td>{{ ucfirst($saleReturn->stock_action) }}</td></tr>
                    <tr><td style="padding:0.4rem 0;color:var(--text-muted);">Total Refund</td><td><strong>KSh {{ number_format($saleReturn->total_refund, 0) }}</strong></td></tr>
                    <tr><td style="padding:0.4rem 0;color:var(--text-muted);">Date</td><td>{{ $saleReturn->created_at->format('d M Y H:i') }}</td></tr>
                    <tr><td style="padding:0.4rem 0;color:var(--text-muted);">Processed by</td><td>{{ $saleReturn->user?->name }}</td></tr>
                    <tr><td style="padding:0.4rem 0;color:var(--text-muted);">Reason</td><td>{{ $saleReturn->reason }}</td></tr>
                    @if($saleReturn->notes)
                    <tr><td style="padding:0.4rem 0;color:var(--text-muted);">Notes</td><td>{{ $saleReturn->notes }}</td></tr>
                    @endif
                </table>
            </div>
        </div>

        <div class="table-card">
            <div class="card-body"><h3>Returned Items</h3></div>
            <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Subtotal</th></tr>
                </thead>
                <tbody>
                    @foreach($saleReturn->items as $item)
                    <tr>
                        <td data-label="Product">{{ $item->product_name }}</td>
                        <td data-label="Qty">{{ $item->quantity_returned }}</td>
                        <td data-label="Unit Price">KSh {{ number_format($item->unit_price, 0) }}</td>
                        <td data-label="Subtotal">KSh {{ number_format($item->subtotal, 0) }}</td>
                    </tr>
                    @endforeach
                    <tr>
                        <td colspan="3"><strong>Total Refund</strong></td>
                        <td data-label="Total Refund"><strong>KSh {{ number_format($saleReturn->total_refund, 0) }}</strong></td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>
@include('partials.etims-refund-status', ['etimsType' => 'sale_return', 'etimsId' => $saleReturn->id])
@endsection
