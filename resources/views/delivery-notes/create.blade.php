@extends('layouts.app')
@section('title', 'New Delivery Note')
@push('styles')
<style>
@media (min-width: 769px) {
    .dn-create-table th, .dn-create-table td { padding: 0.5rem; }
    .dn-create-table tbody td { padding: 0.4rem; }
}
@media (max-width: 900px) {
    .dn-create-layout { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">New Delivery Note</h1>
        </div>
        <a href="{{ route('delivery-notes.index') }}" class="btn btn-secondary">Cancel</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul style="margin:0;padding-left:1.25rem;">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('delivery-notes.store') }}">
        @csrf

        @if($invoice) <input type="hidden" name="invoice_id" value="{{ $invoice->id }}"> @endif
        @if($sale)    <input type="hidden" name="sale_id"    value="{{ $sale->id }}"> @endif

        <div class="dn-create-layout" style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;">
            <div>
                <div class="table-card" style="padding:1.25rem;margin-bottom:1rem;">
                    <h3 style="font-weight:700;margin-bottom:1rem;">Details</h3>

                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <label class="form-label">Customer</label>
                        <select name="customer_id" class="form-control">
                            <option value="">— Walk-in / No Customer —</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}"
                                    {{ old('customer_id', $invoice?->customer_id ?? $sale?->customer_id) == $c->id ? 'selected' : '' }}>
                                    {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <label class="form-label">Delivery Address</label>
                        <textarea name="delivery_address" class="form-control" rows="3"
                                  placeholder="Delivery address / location">{{ old('delivery_address', $invoice?->customer?->address ?? $sale?->customer?->address) }}</textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Any delivery instructions or notes"></textarea>
                    </div>
                </div>

                {{-- Items --}}
                <div class="table-card" style="padding:1.25rem;">
                    <h3 style="font-weight:700;margin-bottom:1rem;">Items</h3>

                    <table class="dn-create-table" style="width:100%;border-collapse:collapse;" id="items-table">
                        <thead>
                            <tr style="border-bottom:2px solid var(--color-border);">
                                <th style="text-align:left;font-size:0.8rem;">Description</th>
                                <th style="text-align:left;font-size:0.8rem;width:100px;">Qty</th>
                                <th style="text-align:left;font-size:0.8rem;width:80px;">Unit</th>
                                <th style="width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody id="items-body">
                            @php
                                $prefillItems = [];
                                if ($invoice) {
                                    foreach ($invoice->items as $item) {
                                        $prefillItems[] = ['description' => $item->description, 'quantity' => $item->quantity, 'unit' => null, 'product_id' => null];
                                    }
                                } elseif ($sale) {
                                    foreach ($sale->items as $item) {
                                        $prefillItems[] = ['description' => $item->product?->name ?? 'Item', 'quantity' => $item->quantity, 'unit' => $item->product?->unit, 'product_id' => $item->product_id];
                                    }
                                } else {
                                    $prefillItems[] = ['description' => '', 'quantity' => 1, 'unit' => '', 'product_id' => null];
                                }
                            @endphp
                            @foreach($prefillItems as $idx => $pi)
                            <tr class="item-row">
                                <td data-label="Description">
                                    <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ $pi['product_id'] }}">
                                    <input type="text" name="items[{{ $idx }}][description]" class="form-control"
                                           value="{{ $pi['description'] }}" required placeholder="Item description">
                                </td>
                                <td data-label="Qty">
                                    <input type="number" name="items[{{ $idx }}][quantity]" class="form-control"
                                           value="{{ $pi['quantity'] }}" min="0.01" step="0.01" required>
                                </td>
                                <td data-label="Unit">
                                    <input type="text" name="items[{{ $idx }}][unit]" class="form-control"
                                           value="{{ $pi['unit'] }}" placeholder="pcs">
                                </td>
                                <td data-label="">
                                    <button type="button" class="btn btn-danger remove-row"
                                            style="font-size:0.75rem;padding:0.2rem 0.5rem;">×</button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <button type="button" id="add-row" class="btn btn-secondary"
                            style="margin-top:0.75rem;font-size:0.85rem;">+ Add Item</button>
                </div>
            </div>

            <div>
                <div class="table-card" style="padding:1.25rem;">
                    @if($invoice)
                        <div style="margin-bottom:1rem;padding:0.75rem;background:var(--color-surface);border-radius:4px;border:1px solid var(--color-border);">
                            <div style="font-size:0.78rem;color:var(--color-text-muted);">Linked Invoice</div>
                            <div style="font-weight:700;">{{ $invoice->invoice_number }}</div>
                        </div>
                    @elseif($sale)
                        <div style="margin-bottom:1rem;padding:0.75rem;background:var(--color-surface);border-radius:4px;border:1px solid var(--color-border);">
                            <div style="font-size:0.78rem;color:var(--color-text-muted);">Linked Sale</div>
                            <div style="font-weight:700;">{{ $sale->invoice_number }}</div>
                        </div>
                    @endif

                    <button type="submit" class="btn btn-primary" style="width:100%;">Create Delivery Note</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let rowIndex = {{ count($prefillItems) }};

document.getElementById('add-row').addEventListener('click', function () {
    const body = document.getElementById('items-body');
    const row = document.createElement('tr');
    row.className = 'item-row';
    row.innerHTML = `
        <td data-label="Description">
            <input type="hidden" name="items[${rowIndex}][product_id]" value="">
            <input type="text" name="items[${rowIndex}][description]" class="form-control" required placeholder="Item description">
        </td>
        <td data-label="Qty">
            <input type="number" name="items[${rowIndex}][quantity]" class="form-control" value="1" min="0.01" step="0.01" required>
        </td>
        <td data-label="Unit">
            <input type="text" name="items[${rowIndex}][unit]" class="form-control" placeholder="pcs">
        </td>
        <td data-label="">
            <button type="button" class="btn btn-danger remove-row" style="font-size:0.75rem;padding:0.2rem 0.5rem;">×</button>
        </td>`;
    body.appendChild(row);
    rowIndex++;
});

document.addEventListener('click', function (e) {
    if (e.target.classList.contains('remove-row')) {
        const rows = document.querySelectorAll('.item-row');
        if (rows.length > 1) e.target.closest('tr').remove();
    }
});
</script>
@endsection
