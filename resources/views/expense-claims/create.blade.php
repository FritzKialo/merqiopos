@extends('layouts.app')
@section('title', 'New Expense Claim')
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">New Expense Claim</h1>
            <p class="page-subtitle">Submit expenses for reimbursement</p>
        </div>
        <a href="{{ route('expense-claims.index') }}" class="btn btn--outline">&larr; Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert--danger">
            <ul style="margin:0;padding-left:1rem;">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('expense-claims.store') }}">
        @csrf
        <div class="card" style="margin-bottom:1.5rem;">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Claim Title *</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title') }}" required placeholder="e.g. Business Trip to Mombasa — June 2026">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                    <h3 style="margin:0;">Expense Items</h3>
                    <button type="button" class="btn btn--outline btn--sm" id="addRow">+ Add Item</button>
                </div>
                <table class="table" id="itemsTable">
                    <thead>
                        <tr>
                            <th>Description *</th>
                            <th style="width:130px;">Date *</th>
                            <th style="width:130px;">Category</th>
                            <th style="width:120px;">Amount (KSh) *</th>
                            <th style="width:40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <tr class="item-row">
                            <td data-label="Description"><input type="text" name="items[0][description]" class="form-control" required placeholder="e.g. Fuel for site visit"></td>
                            <td data-label="Date"><input type="date" name="items[0][expense_date]" class="form-control" value="{{ date('Y-m-d') }}" required></td>
                            <td data-label="Category">
                                <select name="items[0][category]" class="form-control">
                                    <option value="">— Category —</option>
                                    @foreach(['Transport','Accommodation','Meals','Office Supplies','Communication','Other'] as $cat)
                                        <option value="{{ $cat }}">{{ $cat }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td data-label="Amount"><input type="number" name="items[0][amount]" class="form-control item-amount" value="" min="0.01" step="0.01" required placeholder="0.00"></td>
                            <td data-label=""><button type="button" class="btn btn--outline btn--sm remove-row" style="color:var(--color-danger)">&times;</button></td>
                        </tr>
                    </tbody>
                </table>
                <div style="text-align:right;margin-top:0.5rem;">
                    <strong>Total: KSh <span id="totalDisplay">0.00</span></strong>
                </div>
            </div>
        </div>

        <div style="margin-top:1.5rem;display:flex;gap:1rem;justify-content:flex-end;">
            <a href="{{ route('expense-claims.index') }}" class="btn btn--outline">Cancel</a>
            <button type="submit" class="btn btn--primary">Submit Claim</button>
        </div>
    </form>
</div>
@endsection
@push('scripts')
<script>
let rowIdx = 1;
const categories = ['Transport','Accommodation','Meals','Office Supplies','Communication','Other'];

document.getElementById('addRow').addEventListener('click', function() {
    const catOpts = categories.map(c=>`<option value="${c}">${c}</option>`).join('');
    const row = document.createElement('tr');
    row.className = 'item-row';
    row.innerHTML = `
        <td data-label="Description"><input type="text" name="items[${rowIdx}][description]" class="form-control" required></td>
        <td data-label="Date"><input type="date" name="items[${rowIdx}][expense_date]" class="form-control" value="${new Date().toISOString().slice(0,10)}" required></td>
        <td data-label="Category"><select name="items[${rowIdx}][category]" class="form-control"><option value="">— Category —</option>${catOpts}</select></td>
        <td data-label="Amount"><input type="number" name="items[${rowIdx}][amount]" class="form-control item-amount" min="0.01" step="0.01" required placeholder="0.00"></td>
        <td data-label=""><button type="button" class="btn btn--outline btn--sm remove-row" style="color:var(--color-danger)">&times;</button></td>`;
    document.getElementById('itemsBody').appendChild(row);
    row.querySelector('.remove-row').addEventListener('click', ()=>{row.remove();calcTotal();});
    row.querySelector('.item-amount').addEventListener('input', calcTotal);
    rowIdx++;
});

function calcTotal() {
    let t = 0;
    document.querySelectorAll('.item-amount').forEach(i => t += parseFloat(i.value)||0);
    document.getElementById('totalDisplay').textContent = t.toFixed(2);
}

document.querySelectorAll('.remove-row').forEach(b=>b.addEventListener('click',()=>{b.closest('tr').remove();calcTotal();}));
document.querySelectorAll('.item-amount').forEach(i=>i.addEventListener('input',calcTotal));
</script>
@endpush
