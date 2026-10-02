@extends('layouts.app')
@section('title', 'New Purchase Requisition')
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">New Purchase Requisition</h1>
            <p class="page-subtitle">Submit a request to purchase goods or services</p>
        </div>
        <a href="{{ route('purchase-requisitions.index') }}" class="btn btn--outline">&larr; Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert--danger">
            <ul style="margin:0;padding-left:1rem;">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('purchase-requisitions.store') }}">
        @csrf
        <div class="card" style="margin-bottom:1.5rem;">
            <div class="card-body">
                <div class="form-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Urgency *</label>
                        <select name="urgency" class="form-control" required>
                            <option value="low" {{ old('urgency')=='low'?'selected':'' }}>Low</option>
                            <option value="normal" {{ old('urgency','normal')=='normal'?'selected':'' }}>Normal</option>
                            <option value="urgent" {{ old('urgency')=='urgent'?'selected':'' }}>Urgent</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Required By</label>
                        <input type="date" name="required_by" class="form-control" value="{{ old('required_by') }}">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Justification</label>
                    <textarea name="justification" class="form-control" rows="3" placeholder="Explain why this purchase is needed...">{{ old('justification') }}</textarea>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                    <h3 style="margin:0;">Items Requested</h3>
                    <button type="button" class="btn btn--outline btn--sm" id="addRow">+ Add Item</button>
                </div>
                <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Description *</th>
                            <th style="width:90px;">Qty *</th>
                            <th style="width:80px;">Unit</th>
                            <th style="width:140px;">Est. Unit Price</th>
                            <th style="width:160px;">Link to Product</th>
                            <th style="width:40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <tr class="item-row">
                            <td data-label="Description"><input type="text" name="items[0][description]" class="form-control" required placeholder="e.g. A4 Printing Paper"></td>
                            <td data-label="Qty"><input type="number" name="items[0][quantity]" class="form-control" value="1" min="0.01" step="0.01" required></td>
                            <td data-label="Unit"><input type="text" name="items[0][unit]" class="form-control" placeholder="pcs"></td>
                            <td data-label="Est. Unit Price"><input type="number" name="items[0][estimated_unit_price]" class="form-control" value="" min="0" step="0.01" placeholder="0.00"></td>
                            <td data-label="Link to Product">
                                <select name="items[0][product_id]" class="form-control">
                                    <option value="">— None —</option>
                                    @foreach($products as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td data-label="Remove"><button type="button" class="btn btn--outline btn--sm remove-row" style="color:var(--color-danger)">&times;</button></td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <div style="margin-top:1.5rem;display:flex;gap:1rem;justify-content:flex-end;">
            <a href="{{ route('purchase-requisitions.index') }}" class="btn btn--outline">Cancel</a>
            <button type="submit" class="btn btn--primary">Submit Requisition</button>
        </div>
    </form>
</div>
@endsection
@push('scripts')
<script>
let rowIdx = 1;
const productsJson = @json($products->map(fn($p)=>['id'=>$p->id,'name'=>$p->name]));

document.getElementById('addRow').addEventListener('click', function() {
    const opts = productsJson.map(p=>`<option value="${p.id}">${p.name}</option>`).join('');
    const row = document.createElement('tr');
    row.className = 'item-row';
    row.innerHTML = `
        <td data-label="Description"><input type="text" name="items[${rowIdx}][description]" class="form-control" required></td>
        <td data-label="Qty"><input type="number" name="items[${rowIdx}][quantity]" class="form-control" value="1" min="0.01" step="0.01" required></td>
        <td data-label="Unit"><input type="text" name="items[${rowIdx}][unit]" class="form-control"></td>
        <td data-label="Est. Unit Price"><input type="number" name="items[${rowIdx}][estimated_unit_price]" class="form-control" min="0" step="0.01"></td>
        <td data-label="Link to Product"><select name="items[${rowIdx}][product_id]" class="form-control"><option value="">— None —</option>${opts}</select></td>
        <td data-label="Remove"><button type="button" class="btn btn--outline btn--sm remove-row" style="color:var(--color-danger)">&times;</button></td>`;
    document.getElementById('itemsBody').appendChild(row);
    row.querySelector('.remove-row').addEventListener('click', ()=>row.remove());
    rowIdx++;
});
document.querySelectorAll('.remove-row').forEach(b=>b.addEventListener('click',()=>b.closest('tr').remove()));
</script>
@endpush
