@extends('layouts.app')
@section('title', $purchaseRequisition->reference)
@push('styles')
<style>
@media (max-width: 900px) {
    .pr-show-layout { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $purchaseRequisition->reference }}</h1>
            <p class="page-subtitle">Purchase Requisition Details</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            @if($purchaseRequisition->status === 'pending')
                <a href="{{ route('purchase-requisitions.edit', $purchaseRequisition) }}" class="btn btn--outline">Edit</a>
                <form method="POST" action="{{ route('purchase-requisitions.approve', $purchaseRequisition) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn--primary" onclick="return confirm('Approve this requisition?')">&#10003; Approve</button>
                </form>
                <form method="POST" action="{{ route('purchase-requisitions.reject', $purchaseRequisition) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn--outline" style="color:var(--color-danger)" onclick="return confirm('Reject this requisition?')">&#10007; Reject</button>
                </form>
            @endif
            @if($purchaseRequisition->status === 'approved')
                <form method="POST" action="{{ route('purchase-requisitions.convert', $purchaseRequisition) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn--primary">Convert to PO</button>
                </form>
            @endif
            @if(in_array($purchaseRequisition->status, ['pending','rejected']))
                <form method="POST" action="{{ route('purchase-requisitions.destroy', $purchaseRequisition) }}" style="display:inline;"
                      onsubmit="return confirm('Delete requisition {{ addslashes($purchaseRequisition->reference) }}?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn--outline" style="color:var(--color-danger)">Delete</button>
                </form>
            @endif
            <a href="{{ route('purchase-requisitions.index') }}" class="btn btn--outline">&larr; Back</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert--success">{{ session('success') }}</div>
    @endif

    <div class="pr-show-layout" style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;">
        <div>
            <div class="card" style="margin-bottom:1.5rem;">
                <div class="card-body">
                    <h3 style="margin:0 0 1rem;">Details</h3>
                    <table class="table-plain" style="width:100%;border-collapse:collapse;">
                        <tr><td style="padding:6px 0;color:var(--color-text-muted);width:160px;">Reference</td><td><strong>{{ $purchaseRequisition->reference }}</strong></td></tr>
                        <tr><td style="padding:6px 0;color:var(--color-text-muted);">Requested By</td><td>{{ $purchaseRequisition->requestedBy ? $purchaseRequisition->requestedBy->name : '—' }}</td></tr>
                        <tr><td style="padding:6px 0;color:var(--color-text-muted);">Approved By</td><td>{{ $purchaseRequisition->approvedBy ? $purchaseRequisition->approvedBy->name : '—' }}</td></tr>
                        <tr><td style="padding:6px 0;color:var(--color-text-muted);">Required By</td><td>{{ $purchaseRequisition->required_by ? $purchaseRequisition->required_by->format('d M Y') : '—' }}</td></tr>
                        <tr><td style="padding:6px 0;color:var(--color-text-muted);">Created</td><td>{{ $purchaseRequisition->created_at->format('d M Y') }}</td></tr>
                        @if($purchaseRequisition->justification)
                        <tr><td style="padding:6px 0;color:var(--color-text-muted);">Justification</td><td>{{ $purchaseRequisition->justification }}</td></tr>
                        @endif
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h3 style="margin:0 0 1rem;">Requested Items</h3>
                    <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr><th>Description</th><th>Qty</th><th>Unit</th><th>Est. Unit Price</th><th>Est. Total</th><th>Product Link</th></tr>
                        </thead>
                        <tbody>
                            @foreach($purchaseRequisition->items as $item)
                            <tr>
                                <td data-label="Description">{{ $item->description }}</td>
                                <td data-label="Qty">{{ number_format($item->quantity, 2) }}</td>
                                <td data-label="Unit">{{ $item->unit ?: '—' }}</td>
                                <td data-label="Est. Unit Price">{{ $item->estimated_unit_price ? 'KSh '.number_format($item->estimated_unit_price,2) : '—' }}</td>
                                <td data-label="Est. Total">{{ $item->estimated_unit_price ? 'KSh '.number_format($item->quantity*$item->estimated_unit_price,2) : '—' }}</td>
                                <td data-label="Product Link">{{ $item->product ? $item->product->name : '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-body" style="text-align:center;padding:1.5rem;">
                    @php
                        $badgeClass = match($purchaseRequisition->status) {
                            'approved'  => 'badge-success',
                            'rejected'  => 'badge-danger',
                            'converted' => 'badge-secondary',
                            default     => 'badge-warning',
                        };
                        $urgencyClass = match($purchaseRequisition->urgency) {
                            'urgent' => 'badge-danger',
                            'low'    => 'badge-secondary',
                            default  => 'badge-info',
                        };
                    @endphp
                    <div style="margin-bottom:1rem;">
                        <div style="font-size:0.75rem;color:var(--color-text-muted);margin-bottom:4px;">Status</div>
                        <span class="badge {{ $badgeClass }}" style="font-size:1rem;padding:6px 14px;">{{ ucfirst($purchaseRequisition->status) }}</span>
                    </div>
                    <div>
                        <div style="font-size:0.75rem;color:var(--color-text-muted);margin-bottom:4px;">Urgency</div>
                        <span class="badge {{ $urgencyClass }}" style="font-size:1rem;padding:6px 14px;">{{ ucfirst($purchaseRequisition->urgency) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
