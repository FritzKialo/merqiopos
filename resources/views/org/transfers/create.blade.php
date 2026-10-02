@extends('layouts.app')
@section('title', 'New Stock Transfer')
@push('styles')
<style>
/* .form-grid-3's own mobile collapse rule (responsive.css) has no
   !important, so it never actually overrode this element's own inline
   grid-template-columns — an inline style always wins over an external
   rule for the same property regardless of specificity or media query. */
@media (max-width: 768px) {
    .form-grid-3 { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">New Stock Transfer</h1>
            <p class="page-subtitle">Request stock movement between stores</p>
        </div>
        <a href="{{ route('org.transfers.index') }}" class="btn btn--outline">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert--error">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
    @endif

    <form method="POST" action="{{ route('org.transfers.store') }}" id="trfForm">
        @csrf

        <div class="table-card" style="margin-bottom:1.5rem;">
            <div class="card-body">
                <div class="form-grid form-grid-3" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">From Store *</label>
                        <select name="from_business_id" class="form-control" required id="fromStore" onchange="onStoreChange()">
                            <option value="">— Select Source Store —</option>
                            @foreach($stores as $store)
                                <option value="{{ $store->id }}" {{ old('from_business_id') == $store->id ? 'selected' : '' }}>{{ $store->name }}{{ !auth()->user()->canActAsOwner() && in_array($store->id, $managedIds) ? ' (your branch)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">To Store *</label>
                        <select name="to_business_id" class="form-control" required id="toStore" onchange="onStoreChange()">
                            <option value="">— Select Destination Store —</option>
                            @foreach($stores as $store)
                                <option value="{{ $store->id }}" {{ old('to_business_id') == $store->id ? 'selected' : '' }}>{{ $store->name }}{{ !auth()->user()->canActAsOwner() && in_array($store->id, $managedIds) ? ' (your branch)' : '' }}</option>
                            @endforeach
                        </select>
                        @unless(auth()->user()->canActAsOwner())
                        <span class="form-hint">As a branch manager, one side of the transfer must be your branch.</span>
                        @endunless
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="Optional…">
                    </div>
                </div>
            </div>
        </div>

        <div class="table-card" style="margin-bottom:1.5rem;">
            <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;">
                <h3>Items to Transfer</h3>
                <button type="button" onclick="addTrfRow()" class="btn btn--outline">+ Add Item</button>
            </div>
            <p style="padding:0 1rem 0.5rem; font-size:0.85rem; color:var(--color-text-muted, #666);">
                Products are tracked separately at each store — pick the item at the source store, then confirm which product at the destination store it matches. Stock only moves into whichever product you confirm here.
            </p>
            <table class="table" id="trfTable">
                <thead>
                    <tr><th>Product at Source *</th><th>Matching Product at Destination *</th><th>Qty Requested *</th><th>Notes</th><th></th></tr>
                </thead>
                <tbody id="trfBody">
                    <tr>
                        <td data-label="Product at Source">
                            <select name="items[0][product_id]" class="form-control trf-product-select" required onchange="onProductChange(this)">
                                <option value="">— Select From/To stores first —</option>
                            </select>
                        </td>
                        <td data-label="Matching Product at Destination">
                            <select name="items[0][dest_product_id]" class="form-control trf-dest-select" required>
                                <option value="">— Select From/To stores first —</option>
                            </select>
                        </td>
                        <td data-label="Qty Requested"><input type="number" name="items[0][quantity_requested]" class="form-control" min="1" value="1" required></td>
                        <td data-label="Notes"><input type="text" name="items[0][notes]" class="form-control" placeholder="Optional"></td>
                        <td data-label=""><button type="button" onclick="removeTrfRow(this)" class="btn btn--danger btn--sm">Remove</button></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="table-card">
            <div class="card-body">
                <button type="submit" class="btn btn--primary">Submit Transfer Request</button>
                <a href="{{ route('org.transfers.index') }}" class="btn btn--outline">Cancel</a>
            </div>
        </div>
    </form>
</div>

@php
    $productsByStoreJs = [];
    foreach ($productsByStore as $bizId => $items) {
        $productsByStoreJs[$bizId] = $items->map(fn($p) => [
            'id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'stock_qty' => (int) $p->stock_qty,
        ])->values();
    }
@endphp
<script>
// productsByStore[businessId] = [{id, name, sku, stock_qty}, ...]
const productsByStore = @json($productsByStoreJs);
let trfIdx = 1;

function buildOptions(products, placeholder) {
    let html = `<option value="">${placeholder}</option>`;
    (products || []).forEach(p => {
        const label = p.sku ? `${p.name} (${p.sku}) — ${p.stock_qty} in stock` : `${p.name} — ${p.stock_qty} in stock`;
        html += `<option value="${p.id}" data-name="${p.name.replace(/"/g, '&quot;')}" data-sku="${(p.sku || '').replace(/"/g, '&quot;')}">${label}</option>`;
    });
    return html;
}

function onStoreChange() {
    const fromId = document.getElementById('fromStore').value;
    const toId   = document.getElementById('toStore').value;
    const fromProducts = productsByStore[fromId] || [];
    const toProducts   = productsByStore[toId] || [];

    document.querySelectorAll('.trf-product-select').forEach(sel => {
        const current = sel.value;
        sel.innerHTML = buildOptions(fromProducts, fromId ? '— Select Product —' : '— Select From Store first —');
        if (current && fromProducts.some(p => String(p.id) === current)) sel.value = current;
    });
    document.querySelectorAll('.trf-dest-select').forEach(sel => {
        sel.innerHTML = buildOptions(toProducts, toId ? '— Select Matching Product —' : '— Select To Store first —');
    });

    // Re-run auto-suggest for any source product already chosen.
    document.querySelectorAll('.trf-product-select').forEach(sel => { if (sel.value) onProductChange(sel); });
}

// When a source product is picked, suggest (not force) a same-SKU or
// same-name match at the destination — the user still sees and can change
// the destination select, this just saves a click for the common case.
function onProductChange(sourceSelect) {
    const row = sourceSelect.closest('tr');
    const destSelect = row.querySelector('.trf-dest-select');
    if (!destSelect || destSelect.value) return; // don't override a manual choice

    const opt = sourceSelect.selectedOptions[0];
    if (!opt || !opt.value) return;
    const sku = opt.dataset.sku;
    const name = opt.dataset.name;

    const destOptions = Array.from(destSelect.options);
    let match = null;
    if (sku) match = destOptions.find(o => o.dataset.sku && o.dataset.sku === sku);
    if (!match && name) match = destOptions.find(o => o.dataset.name === name);
    if (match) destSelect.value = match.value;
}

function addTrfRow() {
    const i = trfIdx++;
    const fromId = document.getElementById('fromStore').value;
    const toId   = document.getElementById('toStore').value;
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td data-label="Product at Source"><select name="items[${i}][product_id]" class="form-control trf-product-select" required onchange="onProductChange(this)">${buildOptions(productsByStore[fromId], fromId ? '— Select Product —' : '— Select From Store first —')}</select></td>
        <td data-label="Matching Product at Destination"><select name="items[${i}][dest_product_id]" class="form-control trf-dest-select" required>${buildOptions(productsByStore[toId], toId ? '— Select Matching Product —' : '— Select To Store first —')}</select></td>
        <td data-label="Qty Requested"><input type="number" name="items[${i}][quantity_requested]" class="form-control" min="1" value="1" required></td>
        <td data-label="Notes"><input type="text" name="items[${i}][notes]" class="form-control" placeholder="Optional"></td>
        <td data-label=""><button type="button" onclick="removeTrfRow(this)" class="btn btn--danger btn--sm">Remove</button></td>`;
    document.getElementById('trfBody').appendChild(tr);
}

function removeTrfRow(btn) { btn.closest('tr').remove(); }

// Populate immediately if old() input already selected a store (validation-error re-render).
document.addEventListener('DOMContentLoaded', onStoreChange);
</script>
@endsection
