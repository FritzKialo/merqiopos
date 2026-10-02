@extends('layouts.app')
@section('title', $deliveryNote->number)
@push('styles')
<style>
@media (min-width: 769px) {
    .dn-show-table th, .dn-show-table td { padding: 0.75rem 1rem; }
}
@media (max-width: 900px) {
    .dn-show-layout { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $deliveryNote->number }}</h1>
            <p class="page-subtitle">
                @if($deliveryNote->status === 'draft')
                    <span class="badge badge-secondary">Draft</span>
                @elseif($deliveryNote->status === 'dispatched')
                    <span class="badge badge-warning">Dispatched</span>
                @else
                    <span class="badge badge-success">Delivered</span>
                @endif
            </p>
        </div>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            @if($deliveryNote->status === 'draft')
                <form method="POST" action="{{ route('delivery-notes.dispatch', $deliveryNote) }}" style="display:inline;">
                    @csrf @method('PATCH')
                    <button class="btn btn-warning">Mark Dispatched</button>
                </form>
            @endif
            @if($deliveryNote->status === 'dispatched')
                <form method="POST" action="{{ route('delivery-notes.deliver', $deliveryNote) }}" style="display:inline;">
                    @csrf @method('PATCH')
                    <button class="btn btn-success">Mark Delivered</button>
                </form>
            @endif
            <a href="{{ route('delivery-notes.pdf', $deliveryNote) }}" class="btn btn-secondary" target="_blank">Print / PDF</a>
            <a href="{{ route('delivery-notes.index') }}" class="btn btn-secondary">Back</a>
        </div>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

    <div class="dn-show-layout" style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;">
        <div>
            {{-- Items --}}
            <div class="table-card" style="margin-bottom:1.5rem;">
                <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--color-border);font-weight:700;">Items</div>
                <table class="dn-show-table" style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:1px solid var(--color-border);">
                            <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">#</th>
                            <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Description</th>
                            <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Qty</th>
                            <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deliveryNote->items as $i => $item)
                        <tr style="border-bottom:1px solid var(--color-border);">
                            <td data-label="#" style="color:var(--color-text-muted);">{{ $i + 1 }}</td>
                            <td data-label="Description">{{ $item->description }}</td>
                            <td data-label="Qty" style="text-align:right;font-weight:600;">{{ number_format($item->quantity, 2) }}</td>
                            <td data-label="Unit" style="color:var(--color-text-muted);">{{ $item->unit ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Timeline --}}
            <div class="table-card" style="padding:1.25rem;">
                <h3 style="font-weight:700;margin-bottom:1.25rem;">Timeline</h3>
                <div style="display:flex;flex-direction:column;gap:0.75rem;">
                    <div style="display:flex;align-items:center;gap:1rem;">
                        <div style="width:12px;height:12px;border-radius:50%;background:var(--color-success);flex-shrink:0;"></div>
                        <div>
                            <div style="font-weight:600;font-size:0.9rem;">Created</div>
                            <div style="color:var(--color-text-muted);font-size:0.82rem;">{{ $deliveryNote->created_at->format('d M Y H:i') }} by {{ $deliveryNote->user?->name }}</div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:1rem;">
                        <div style="width:12px;height:12px;border-radius:50%;background:{{ $deliveryNote->dispatched_at ? 'var(--color-warning)' : 'var(--color-border)' }};flex-shrink:0;"></div>
                        <div>
                            <div style="font-weight:600;font-size:0.9rem;color:{{ $deliveryNote->dispatched_at ? 'inherit' : 'var(--color-text-muted)' }};">Dispatched</div>
                            @if($deliveryNote->dispatched_at)
                                <div style="color:var(--color-text-muted);font-size:0.82rem;">{{ $deliveryNote->dispatched_at->format('d M Y H:i') }}</div>
                            @else
                                <div style="color:var(--color-text-muted);font-size:0.82rem;">Pending</div>
                            @endif
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:1rem;">
                        <div style="width:12px;height:12px;border-radius:50%;background:{{ $deliveryNote->delivered_at ? 'var(--color-success)' : 'var(--color-border)' }};flex-shrink:0;"></div>
                        <div>
                            <div style="font-weight:600;font-size:0.9rem;color:{{ $deliveryNote->delivered_at ? 'inherit' : 'var(--color-text-muted)' }};">Delivered</div>
                            @if($deliveryNote->delivered_at)
                                <div style="color:var(--color-text-muted);font-size:0.82rem;">{{ $deliveryNote->delivered_at->format('d M Y H:i') }}</div>
                            @else
                                <div style="color:var(--color-text-muted);font-size:0.82rem;">Pending</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="table-card" style="padding:1.25rem;margin-bottom:1rem;">
                <h3 style="font-weight:700;margin-bottom:0.75rem;">Details</h3>
                <div style="font-size:0.85rem;display:flex;flex-direction:column;gap:0.5rem;">
                    <div><span class="text-muted">Customer:</span><br>
                        <strong>{{ $deliveryNote->customer?->name ?? '—' }}</strong></div>
                    @if($deliveryNote->delivery_address)
                    <div><span class="text-muted">Delivery Address:</span><br>
                        {{ $deliveryNote->delivery_address }}</div>
                    @endif
                    @if($deliveryNote->invoice)
                    <div><span class="text-muted">Invoice:</span><br>
                        <a href="{{ route('invoices.show', $deliveryNote->invoice) }}">{{ $deliveryNote->invoice->invoice_number }}</a></div>
                    @endif
                    @if($deliveryNote->notes)
                    <div><span class="text-muted">Notes:</span><br>
                        {{ $deliveryNote->notes }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="table-card" style="padding:1.25rem;margin-top:1rem;">
        @include('partials.attachments', ['modelType' => 'DeliveryNote', 'modelId' => $deliveryNote->id])
    </div>
</div>
@endsection
