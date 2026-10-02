@extends('layouts.app')
@section('title', 'Edit ' . $purchaseRequisition->reference)
@push('styles')
<style>
@media (max-width: 700px) {
    .pr-edit-grid { grid-template-columns: 1fr 1fr !important; }
}
@media (max-width: 480px) {
    .pr-edit-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Edit {{ $purchaseRequisition->reference }}</h1>
        </div>
        <a href="{{ route('purchase-requisitions.show', $purchaseRequisition) }}" class="btn btn--outline">&larr; Back</a>
    </div>

    <form method="POST" action="{{ route('purchase-requisitions.update', $purchaseRequisition) }}">
        @csrf @method('PUT')
        <div class="card" style="margin-bottom:1.5rem;">
            <div class="card-body">
                <div class="pr-edit-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Urgency</label>
                        <select name="urgency" class="form-control">
                            <option value="low" {{ $purchaseRequisition->urgency=='low'?'selected':'' }}>Low</option>
                            <option value="normal" {{ $purchaseRequisition->urgency=='normal'?'selected':'' }}>Normal</option>
                            <option value="urgent" {{ $purchaseRequisition->urgency=='urgent'?'selected':'' }}>Urgent</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Required By</label>
                        <input type="date" name="required_by" class="form-control" value="{{ $purchaseRequisition->required_by ? $purchaseRequisition->required_by->format('Y-m-d') : '' }}">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Justification</label>
                    <textarea name="justification" class="form-control" rows="3">{{ $purchaseRequisition->justification }}</textarea>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                    <h3 style="margin:0;">Items</h3>
                    <button type="button" class="btn btn--outline btn--sm" id="addRow">+ Add Item</button>
                </div>
                <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Est. Price</th><th>Product</th><th></th></tr></thead>
                    <tbody id="itemsBody">
                        @foreach($purchaseRequisition->items as $i => $item)
                        <tr class="item-row">
                            <td data-label="Description"><input type="text" name="items[{{ $i }}][description]" class="form-control" value="{{ $item->description }}" required></td>
                            <td data-label="Qty"><input type="number" name="items[{{ $i }}][quantity]" class="form-control" value="{{ $item->quantity }}" min="0.01" step="0.01" required></td>
                            <td data-label="Unit"><input type="text" name="items[{{ $i }}][unit]" class="form-control" value="{{ $item->unit }}"></td>
                            <td data-label="Est. Price"><input type="number" name="items[{{ $i }}][estimated_unit_price]" class="form-control" value="{{ $item->estimated_unit_price }}" min="0" step="0.01"></td>
                            <td data-label="Product">
                                <select name="items[{{ $i }}][product_id]" class="form-control">
                                    <option value="">— None —</option>
                                    @foreach($products as $p)
                                        <option value="{{ $p->id }}" {{ $item->product_id==$p->id?'selected':'' }}>{{ $p->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td data-label="Remove"><button type="button" class="btn btn--outline btn--sm remove-row" style="color:var(--color-danger)">&times;</button></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        <div style="margin-top:1.5rem;display:flex;gap:1rem;justify-content:flex-end;">
            <a href="{{ route('purchase-requisitions.show', $purchaseRequisition) }}" class="btn btn--outline">Cancel</a>
            <button type="submit" class="btn btn--primary">Save Changes</button>
        </div>
    </form>
</div>
@endsection
@push('scripts')
<script>
let rowIdx = {{ $purchaseRequisition->items->count() }};
const productsJson = @json($products->map(fn($p)=>['id'=>$p->id,'name'=>$p->name]));
document.getElementById('addRow').addEventListener('click', function() {
    const opts = productsJson.map(p=>`<option value="${p.id}">${p.name}</option>`).join('');
    const row = document.createElement('tr');
    row.className = 'item-row';
    row.innerHTML = `<td data-label="Description"><input type="text" name="items[${rowIdx}][description]" class="form-control" required></td><td data-label="Qty"><input type="number" name="items[${rowIdx}][quantity]" class="form-control" value="1" min="0.01" step="0.01" required></td><td data-label="Unit"><input type="text" name="items[${rowIdx}][unit]" class="form-control"></td><td data-label="Est. Price"><input type="number" name="items[${rowIdx}][estimated_unit_price]" class="form-control" min="0" step="0.01"></td><td data-label="Product"><select name="items[${rowIdx}][product_id]" class="form-control"><option value="">— None —</option>${opts}</select></td><td data-label="Remove"><button type="button" class="btn btn--outline btn--sm remove-row" style="color:var(--color-danger)">&times;</button></td>`;
    document.getElementById('itemsBody').appendChild(row);
    row.querySelector('.remove-row').addEventListener('click', ()=>row.remove());
    rowIdx++;
});
document.querySelectorAll('.remove-row').forEach(b=>b.addEventListener('click',()=>b.closest('tr').remove()));
</script>
@endpush
