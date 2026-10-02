@extends('layouts.app')
@section('title', $purchaseOrder->po_number)

@section('content')

{{-- Header --}}
<div class="page-header">
    <div>
        <h1 class="page-title" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            
            {{ $purchaseOrder->po_number }}

            {{-- Status Badge --}}
            @if($purchaseOrder->status === 'draft')
                <span class="badge badge-neutral" style="font-size: 0.8rem;">Draft</span>
            @elseif($purchaseOrder->status === 'ordered')
                <span class="badge badge-blue" style="font-size: 0.8rem;">Ordered</span>
            @elseif($purchaseOrder->status === 'partially_received')
                <span class="badge badge-warning" style="font-size: 0.8rem;">Partially Received</span>
            @elseif($purchaseOrder->status === 'received')
                <span class="badge badge-success" style="font-size: 0.8rem;">Received</span>
            @elseif($purchaseOrder->status === 'cancelled')
                <span class="badge badge-danger" style="font-size: 0.8rem;">Cancelled</span>
            @endif

            {{-- Payment Badge --}}
            @if($purchaseOrder->payment_status === 'paid')
                <span class="badge badge-success" style="font-size: 0.8rem;">Paid</span>
            @elseif($purchaseOrder->payment_status === 'partial')
                <span class="badge badge-warning" style="font-size: 0.8rem;">Partial Payment</span>
            @else
                <span class="badge badge-danger" style="font-size: 0.8rem;">Unpaid</span>
            @endif
        </h1>
        <p class="page-subtitle">
            Created {{ $purchaseOrder->created_at->format('d M Y, g:i A') }}
            @if($purchaseOrder->user)
                by {{ $purchaseOrder->user->name }}
            @endif
        </p>
    </div>
    <div class="action-buttons" style="flex-wrap: wrap; gap: 8px;">
        {{-- Receive Stock --}}
        @if(!in_array($purchaseOrder->status, ['received', 'cancelled']))
            <a href="#receive-stock-section"
               class="btn btn--primary btn--sm"
               onclick="document.getElementById('receiveSection').style.display='block'; this.scrollIntoView({behavior:'smooth'});">
                
                Receive Stock
            </a>
        @endif

        {{-- Record Payment --}}
        @if($purchaseOrder->payment_status !== 'paid' && $purchaseOrder->status !== 'cancelled')
            <button
                class="btn btn--outline btn--sm"
                onclick="document.getElementById('paymentSection').style.display='block'; document.getElementById('paymentSection').scrollIntoView({behavior:'smooth'});">
                
                Record Payment
            </button>
        @endif

        {{-- Cancel PO --}}
        @if(in_array($purchaseOrder->status, ['draft', 'ordered']))
            <form method="POST" action="{{ route('purchases.cancel', $purchaseOrder) }}"
                  onsubmit="return confirm('Cancel this purchase order?')" style="display:inline;">
                @csrf
                {{-- Was missing @method('PATCH') entirely — the registered
                route is PATCH-only (routes/web.php), so this submitted as a
                plain POST and the "Cancel PO" button has always thrown a 405
                Method Not Allowed error. --}}
                @method('PATCH')
                <button type="submit" class="btn btn--outline btn--sm" style="color: var(--color-danger); border-color: var(--color-danger);">
                    
                    Cancel PO
                </button>
            </form>
        @endif

        <a href="{{ route('purchases.index') }}" class="btn btn--outline btn--sm">
            &#8592; Back
        </a>
    </div>
</div>

<div class="sale-layout">

    {{-- LEFT: Main Content --}}
    <div style="flex: 1; min-width: 0;">

        {{-- PO Info Card --}}
        <div class="report-section" style="margin-bottom: var(--space-md); padding: var(--space-lg);">
            <div class="form-section-title" style="margin-top: 0;">Order Information</div>
            <div class="form-grid-2" style="gap: var(--space-md);">
                <div>
                    <div style="font-size: 0.8rem; color: var(--color-text-muted); margin-bottom: 4px;">Supplier</div>
                    <div style="font-weight: 600;">
                        @if($purchaseOrder->supplier)
                            <a href="{{ route('suppliers.show', $purchaseOrder->supplier) }}"
                               style="color: var(--color-primary);">
                                {{ $purchaseOrder->supplier->name }}
                            </a>
                        @else
                            <span style="color: var(--color-text-muted);">— Direct Purchase —</span>
                        @endif
                    </div>
                </div>
                <div>
                    <div style="font-size: 0.8rem; color: var(--color-text-muted); margin-bottom: 4px;">Order Date</div>
                    <div style="font-weight: 600;">{{ $purchaseOrder->order_date->format('d M Y') }}</div>
                </div>
                <div>
                    <div style="font-size: 0.8rem; color: var(--color-text-muted); margin-bottom: 4px;">Expected Date</div>
                    <div style="font-weight: 600;">{{ $purchaseOrder->expected_date ? $purchaseOrder->expected_date->format('d M Y') : '—' }}</div>
                </div>
                <div>
                    <div style="font-size: 0.8rem; color: var(--color-text-muted); margin-bottom: 4px;">Received Date</div>
                    <div style="font-weight: 600;">{{ $purchaseOrder->received_date ? $purchaseOrder->received_date->format('d M Y') : '—' }}</div>
                </div>
            </div>
        </div>

        {{-- Items Table --}}
        <div class="report-section" style="margin-bottom: var(--space-md);">
            <div style="padding: var(--space-md) var(--space-lg); border-bottom: 1px solid var(--color-border);">
                <h3 style="margin: 0; font-size: 1rem;"> Order Items</h3>
            </div>
            <div class="table-wrapper">
                <table class="table-responsive-cards">
                    <thead>
                        <tr>
                            <th>Product / Item</th>
                            <th>Ordered</th>
                            <th>Received</th>
                            <th>Pending</th>
                            <th>Unit Cost</th>
                            <th>Subtotal</th>
                            <th>Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchaseOrder->items as $item)
                        <tr>
                            <td data-label="Product / Item">
                                <div style="font-weight: 600;">{{ $item->product_name }}</div>
                                @if($item->product)
                                    <div style="font-size: 0.75rem; color: var(--color-text-muted);">
                                        SKU: {{ $item->product->sku ?? '—' }}
                                    </div>
                                @endif
                            </td>
                            <td data-label="Ordered">{{ $item->quantity_ordered }}</td>
                            <td data-label="Received">
                                <span class="{{ $item->quantity_received > 0 ? 'text-success' : '' }}" style="font-weight:600;">
                                    {{ $item->quantity_received }}
                                </span>
                            </td>
                            <td data-label="Pending">
                                @php $pending = $item->quantityPending(); @endphp
                                <span class="{{ $pending > 0 ? 'text-warn' : 'text-muted' }}" style="font-weight:{{ $pending > 0 ? '600' : '400' }};">
                                    {{ $pending }}
                                </span>
                            </td>
                            <td data-label="Unit Cost">KSh {{ number_format($item->unit_cost, 2) }}</td>
                            <td data-label="Subtotal"><strong>KSh {{ number_format($item->subtotal, 2) }}</strong></td>
                            <td data-label="Progress" style="min-width: 100px;">
                                @php
                                    $pct = $item->quantity_ordered > 0
                                        ? min(100, round(($item->quantity_received / $item->quantity_ordered) * 100))
                                        : 0;
                                    $pctClass = $pct >= 100 ? 'progress-bar-fill--full' : ($pct > 0 ? 'progress-bar-fill--partial' : '');
                                @endphp
                                <div class="progress-bar">
                                    <div class="progress-bar-fill {{ $pctClass }}" style="width: {{ $pct }}%;"></div>
                                </div>
                                <div class="text-muted" style="font-size: 0.7rem; margin-top: 2px; text-align: center;">{{ $pct }}%</div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Notes --}}
        @if($purchaseOrder->notes)
            <div class="report-section" style="margin-bottom: var(--space-md); padding: var(--space-lg);">
                <strong> Notes</strong>
                <p style="margin-top: 8px; color: var(--color-text-muted); font-size: 0.875rem;">
                    {{ $purchaseOrder->notes }}
                </p>
            </div>
        @endif

        {{-- Receive Stock Section --}}
        @if(!in_array($purchaseOrder->status, ['received', 'cancelled']))
        <div id="receiveSection" style="display: none; margin-bottom: var(--space-md);" id="receive-stock-section">
            <div class="report-section">
                <div style="padding: var(--space-md) var(--space-lg); border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; font-size: 1rem; color: var(--color-primary);">
                         Receive Stock
                    </h3>
                    <button type="button" onclick="document.getElementById('receiveSection').style.display='none';"
                            style="background: none; border: none; cursor: pointer; color: var(--color-text-muted); font-size: 1.2rem;">&times;</button>
                </div>
                <div style="padding: var(--space-lg);">
                    <p style="font-size: 0.875rem; color: var(--color-text-muted); margin-bottom: var(--space-md);">
                        Enter the quantity received for each item. Leave as 0 to skip an item. Stock levels will be updated automatically for linked products.
                    </p>
                    <form method="POST" action="{{ route('purchases.receive', $purchaseOrder) }}">
                        @csrf
                        <div class="items-table-wrapper">
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Ordered</th>
                                        <th>Already Received</th>
                                        <th>Pending</th>
                                        <th>Qty Received Now</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($purchaseOrder->items as $item)
                                    <input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $item->id }}">
                                    <tr>
                                        <td data-label="Item">
                                            <strong>{{ $item->product_name }}</strong>
                                        </td>
                                        <td data-label="Ordered">{{ $item->quantity_ordered }}</td>
                                        <td data-label="Already Received">{{ $item->quantity_received }}</td>
                                        <td data-label="Pending">
                                            <span class="{{ $item->quantityPending() > 0 ? 'text-warn' : 'text-muted' }}" style="font-weight:{{ $item->quantityPending() > 0 ? '600' : '400' }};">
                                                {{ $item->quantityPending() }}
                                            </span>
                                        </td>
                                        <td data-label="Qty Received Now">
                                            <input
                                                type="number"
                                                name="items[{{ $loop->index }}][quantity_received]"
                                                class="form-control"
                                                style="width: 90px; font-size: 0.875rem;"
                                                value="0"
                                                min="0"
                                                max="{{ $item->quantityPending() }}"
                                                {{ $item->quantityPending() <= 0 ? 'disabled' : '' }}>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="form-actions" style="margin-top: var(--space-md);">
                            <button type="submit" class="btn btn-primary">
                                
                                Confirm Receipt
                            </button>
                            <button type="button" onclick="document.getElementById('receiveSection').style.display='none';" class="btn btn-outline">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif

        {{-- Record Payment Section --}}
        @if($purchaseOrder->payment_status !== 'paid' && $purchaseOrder->status !== 'cancelled')
        <div id="paymentSection" style="display: none; margin-bottom: var(--space-md);">
            <div class="report-section">
                <div style="padding: var(--space-md) var(--space-lg); border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; font-size: 1rem; color: var(--color-primary);">
                         Record Payment
                    </h3>
                    <button type="button" onclick="document.getElementById('paymentSection').style.display='none';"
                            style="background: none; border: none; cursor: pointer; color: var(--color-text-muted); font-size: 1.2rem;">&times;</button>
                </div>
                <div style="padding: var(--space-lg);">
                    <p style="font-size: 0.875rem; color: var(--color-text-muted); margin-bottom: var(--space-md);">
                        Outstanding balance:
                        <strong class="text-danger">
                            KSh {{ number_format($purchaseOrder->amountDue(), 2) }}
                        </strong>
                    </p>
                    <form method="POST" action="{{ route('purchases.payment', $purchaseOrder) }}">
                        @csrf
                        <div class="form-group">
                            <label class="form-label" for="pay_amount">Amount (KSh) *</label>
                            <input
                                type="number"
                                id="pay_amount"
                                name="amount"
                                class="form-control {{ $errors->has('amount') ? 'is-invalid' : '' }}"
                                value="{{ old('amount', $purchaseOrder->amountDue()) }}"
                                min="0.01"
                                step="0.01"
                                max="{{ $purchaseOrder->amountDue() }}"
                                required
                                style="max-width: 250px;">
                            @error('amount')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                
                                Confirm Payment
                            </button>
                            <button type="button" onclick="document.getElementById('paymentSection').style.display='none';" class="btn btn-outline">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif

    </div>

    {{-- RIGHT: Totals --}}
    <div style="width: 280px; flex-shrink: 0;">
        <div class="summary-panel" style="position: sticky; top: 20px;">
            <h3> Order Totals</h3>

            <div class="summary-row">
                <span>Subtotal</span>
                <span>KSh {{ number_format($purchaseOrder->subtotal, 2) }}</span>
            </div>

            @if($purchaseOrder->tax_amount > 0)
            <div class="summary-row">
                <span>Tax</span>
                <span>KSh {{ number_format($purchaseOrder->tax_amount, 2) }}</span>
            </div>
            @endif

            <div class="summary-row total">
                <span>Total</span>
                <span>KSh {{ number_format($purchaseOrder->total, 2) }}</span>
            </div>

            <div class="summary-row paid" style="border-top: 1px solid var(--color-border); margin-top: 8px; padding-top: 8px;">
                <span>Paid</span>
                <span class="text-success" style="font-weight: 700;">
                    KSh {{ number_format($purchaseOrder->amount_paid, 2) }}
                </span>
            </div>

            <div class="summary-row balance">
                <span>Balance Due</span>
                <span class="{{ $purchaseOrder->amountDue() > 0 ? 'text-danger' : 'text-success' }}" style="font-weight: 700;">
                    KSh {{ number_format($purchaseOrder->amountDue(), 2) }}
                </span>
            </div>

            <div style="margin-top: var(--space-md); padding-top: var(--space-md); border-top: 1px solid var(--color-border);">
                <div class="text-muted" style="font-size: 0.8rem; margin-bottom: 8px;">
                    <strong>Items:</strong> {{ $purchaseOrder->items->count() }}
                </div>
                <div class="text-muted" style="font-size: 0.8rem;">
                    <strong>Total Units Ordered:</strong> {{ $purchaseOrder->items->sum('quantity_ordered') }}
                </div>
                <div class="text-muted" style="font-size: 0.8rem; margin-top: 4px;">
                    <strong>Total Units Received:</strong>
                    <span class="{{ $purchaseOrder->items->sum('quantity_received') > 0 ? 'text-success' : '' }}">
                        {{ $purchaseOrder->items->sum('quantity_received') }}
                    </span>
                </div>
            </div>

            @if($purchaseOrder->supplier)
            <div style="margin-top: var(--space-md); padding-top: var(--space-md); border-top: 1px solid var(--color-border);">
                <a href="{{ route('suppliers.show', $purchaseOrder->supplier) }}"
                   class="btn btn--outline btn--sm" style="width: 100%; justify-content: center;">
                    
                    View Supplier
                </a>
            </div>
            @endif
        </div>
    </div>

</div>

<div class="table-card" style="margin-top: var(--space-md);">
    <div class="card-body">
        @include('partials.attachments', ['modelType' => 'PurchaseOrder', 'modelId' => $purchaseOrder->id])
    </div>
</div>

@endsection
