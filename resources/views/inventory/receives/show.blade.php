@extends('layouts.app')
@section('title', $stockReceive->receive_number)
@push('styles')
<style>
@media (max-width: 900px) {
    .receive-show-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $stockReceive->receive_number }}</h1>
            <p class="page-subtitle">Stock receive details</p>
        </div>
        <a href="{{ route('receives.index') }}" class="btn btn--outline">Back</a>
    </div>

    <div class="receive-show-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">
        <div class="table-card">
            <div class="card-body">
                <h3 style="margin-bottom:1rem;">Receive Info</h3>
                <table class="table-plain" style="width:100%;border-collapse:collapse;">
                    <tr><td style="padding:.4rem 0;color:var(--text-muted);">Receive #</td><td><strong>{{ $stockReceive->receive_number }}</strong></td></tr>
                    <tr><td style="padding:.4rem 0;color:var(--text-muted);">Date</td><td>{{ $stockReceive->received_date->format('d M Y') }}</td></tr>
                    <tr><td style="padding:.4rem 0;color:var(--text-muted);">Supplier</td><td>{{ $stockReceive->supplier?->name ?? '—' }}</td></tr>
                    <tr><td style="padding:.4rem 0;color:var(--text-muted);">Purchase Order</td><td>{{ $stockReceive->purchaseOrder?->po_number ?? '—' }}</td></tr>
                    <tr><td style="padding:.4rem 0;color:var(--text-muted);">Invoice Ref</td><td>{{ $stockReceive->invoice_ref ?? '—' }}</td></tr>
                    @role('owner','overall_manager','manager')
                    <tr><td style="padding:.4rem 0;color:var(--text-muted);">Total Cost</td><td><strong>KSh {{ number_format($stockReceive->total_cost, 2) }}</strong></td></tr>
                    @endrole
                    <tr><td style="padding:.4rem 0;color:var(--text-muted);">Received By</td><td>{{ $stockReceive->user?->name }}</td></tr>
                    @if($stockReceive->notes)
                    <tr><td style="padding:.4rem 0;color:var(--text-muted);">Notes</td><td>{{ $stockReceive->notes }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <div class="table-card">
        <div class="card-body"><h3>Items Received</h3></div>
        <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Qty Received</th>
                    @role('owner','overall_manager','manager')
                    <th>Unit Cost</th>
                    <th>Subtotal</th>
                    @endrole
                </tr>
            </thead>
            <tbody>
                @foreach($stockReceive->items as $item)
                <tr>
                    <td data-label="Product">{{ $item->product_name }}</td>
                    <td data-label="Qty Received">
                        {{ $item->quantity_received }}
                        @if($item->received_unit)
                            <span style="color:var(--color-text-muted); font-size:12px;">({{ $item->received_qty_in_unit }} {{ $item->received_unit }})</span>
                        @endif
                    </td>
                    @role('owner','overall_manager','manager')
                    <td data-label="Unit Cost">KSh {{ number_format($item->unit_cost, 2) }}</td>
                    <td data-label="Subtotal">KSh {{ number_format($item->subtotal, 2) }}</td>
                    @endrole
                </tr>
                @endforeach
                @role('owner','overall_manager','manager')
                <tr>
                    <td colspan="3"><strong>Total</strong></td>
                    <td data-label="Total"><strong>KSh {{ number_format($stockReceive->total_cost, 2) }}</strong></td>
                </tr>
                @endrole
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
