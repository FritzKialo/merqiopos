@extends('layouts.app')
@section('title', 'Edit Expense Claim')
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Edit Expense Claim</h1>
            <p class="page-subtitle">{{ $expenseClaim->reference }} — draft</p>
        </div>
        <a href="{{ route('expense-claims.show', $expenseClaim) }}" class="btn btn--outline">&larr; Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert--danger">
            <ul style="margin:0;padding-left:1rem;">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('expense-claims.update', $expenseClaim) }}">
        @csrf
        @method('PUT')
        <div class="card" style="margin-bottom:1.5rem;">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Claim Title *</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title', $expenseClaim->title) }}" required>
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
                        @foreach($expenseClaim->items as $i => $item)
                        <tr class="item-row">
                            <td data-label="Description"><input type="text" name="items[{{ $i }}][description]" class="form-control" required value="{{ $item->description }}"></td>
                            <td data-label="Date"><input type="date" name="items[{{ $i }}][expense_date]" class="form-control" value="{{ \Carbon\Carbon::parse($item->expense_date)->format('Y-m-d') }}" required></td>
                            <td data-label="Category">
                                <select name="items[{{ $i }}][category]" class="form-control">
                                    <option value="">— Category —</option>
                                    @foreach(['Transport','Accommodation','Meals','Office Supplies','Communication','Other'] as $cat)
                                        <option value="{{ $cat }}" {{ $item->category === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td data-label="Amount"><input type="number" name="items[{{ $i }}][amount]" class="form-control item-amount" value="{{ $item->amount }}" min="0.01" step="0.01" required></td>
                            <td data-label=""><button type="button" class="btn btn--outline btn--sm remove-row" style="color:var(--color-danger)">&times;</button></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div style="text-align:right;margin-top:0.5rem;">
                    <strong>Total: KSh <span id="totalDisplay">0.00</span></strong>
                </div>
            </div>
        </div>

        <div style="margin-top:1.5rem;display:flex;gap:1rem;justify-content:flex-end;">
            <a href="{{ route('expense-claims.show', $expenseClaim) }}" class="btn btn--outline">Cancel</a>
            <button type="submit" class="btn btn--primary">Update Claim</button>
        </div>
    </form>
</div>
@endsection
@push('scripts')
<script>
let rowIdx = {{ $expenseClaim->items->count() }};
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
calcTotal();
</script>
@endpush
