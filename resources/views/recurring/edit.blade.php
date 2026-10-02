@extends('layouts.app')
@section('title', 'Edit Recurring Invoice')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/sales.css') }}">
    <style>
        .ri-layout{ display:grid; grid-template-columns:1fr 340px; gap:var(--space-5); align-items:start; }
        @media(max-width:1024px){ .ri-layout{ grid-template-columns:1fr; } }
        .form-card{ background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-lg); padding:var(--space-5); box-shadow:var(--shadow-xs); }
        .form-section-title{ font-size:var(--text-sm); font-weight:700; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:.07em; margin-bottom:var(--space-4); padding-bottom:var(--space-3); border-bottom:1px solid var(--color-border); }
        .items-table-wrapper{ overflow-x:auto; margin-bottom:var(--space-4); }
        .items-table{ width:100%; border-collapse:collapse; }
        .items-table th{ background:var(--color-surface-2); padding:8px 10px; text-align:left; font-size:var(--text-2xs); font-weight:700; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:.07em; border-bottom:1px solid var(--color-border); }
        .items-table td{ padding:6px 8px; border-bottom:1px solid var(--color-border); vertical-align:middle; }
        .items-table tr:last-child td{ border-bottom:none; }
        .add-item-btn{ background:none; border:1px dashed var(--color-border); border-radius:var(--radius-md); padding:8px 16px; color:var(--color-primary); font-size:var(--text-sm); font-weight:600; cursor:pointer; width:100%; transition:all .18s; }
        .add-item-btn:hover{ background:var(--color-primary-light); }
        .remove-row-btn{ background:none; border:none; color:var(--color-danger); cursor:pointer; font-size:1.2rem; padding:2px 6px; border-radius:var(--radius-sm); }
        .freq-cards{ display:grid; grid-template-columns:repeat(4,1fr); gap:var(--space-2); }
        @media(max-width:600px){ .freq-cards{ grid-template-columns:repeat(2,1fr); } }
        .freq-card{ border:1.5px solid var(--color-border); border-radius:var(--radius-md); padding:var(--space-3); cursor:pointer; transition:all .18s; text-align:center; }
        .freq-card.selected{ border-color:var(--color-primary); background:var(--color-primary-light); }
        .freq-card input{ display:none; }
        .freq-card-icon{ font-size:1.4rem; color:var(--color-primary); margin-bottom:4px; }
        .freq-card-label{ font-weight:700; font-size:var(--text-sm); }
        .freq-card-desc{ font-size:var(--text-xs); color:var(--color-text-muted); }
        .summary-panel{ background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-lg); padding:var(--space-5); box-shadow:var(--shadow-xs); position:sticky; top:var(--space-4); }
        .s-row{ display:flex; justify-content:space-between; padding:var(--space-2) 0; font-size:var(--text-sm); color:var(--color-text-muted); border-bottom:1px solid var(--color-border); }
        .s-row:last-of-type{ border-bottom:none; }
        .s-row.total{ font-weight:700; font-size:var(--text-base); color:var(--color-text); }
    </style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title"> Edit Recurring Invoice</h1>
        <p class="page-subtitle">{{ $recurringInvoice->title }}</p>
    </div>
    <a href="{{ route('recurring.show', $recurringInvoice) }}" class="btn btn--outline"> Back</a>
</div>

<form method="POST" action="{{ route('recurring.update', $recurringInvoice) }}" id="riForm">
@csrf @method('PUT')
<div class="ri-layout">
    <div>
        <div class="form-card" style="margin-bottom:var(--space-4);">
            <p class="form-section-title">Invoice Details</p>
            <div class="form-group">
                <label class="form-label">Title <span style="color:var(--color-danger);">*</span></label>
                <input type="text" name="title" class="form-control" value="{{ old('title', $recurringInvoice->title) }}" required>
            </div>
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">Customer</label>
                    <select name="customer_id" class="form-control">
                        <option value="">— No customer —</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" {{ old('customer_id', $recurringInvoice->customer_id) == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Next Run Date <span style="color:var(--color-danger);">*</span></label>
                    <input type="date" name="next_run_date" class="form-control" value="{{ old('next_run_date', $recurringInvoice->next_run_date->toDateString()) }}" required>
                </div>
            </div>
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="{{ old('end_date', $recurringInvoice->end_date?->toDateString()) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" value="{{ old('notes', $recurringInvoice->notes) }}">
                </div>
            </div>
        </div>

        <div class="form-card" style="margin-bottom:var(--space-4);">
            <p class="form-section-title">Frequency</p>
            <div class="freq-cards">
                @foreach(['weekly'=>['ph-calendar','Weekly','Every 7 days'],'monthly'=>['ph-calendar-blank','Monthly','Same day each month'],'quarterly'=>['ph-calendar-dots','Quarterly','Every 3 months'],'yearly'=>['ph-calendar-star','Yearly','Once a year']] as $val => $opt)
                <label class="freq-card {{ old('frequency',$recurringInvoice->frequency) === $val ? 'selected' : '' }}">
                    <input type="radio" name="frequency" value="{{ $val }}" {{ old('frequency',$recurringInvoice->frequency) === $val ? 'checked' : '' }}>
                    <div class="freq-card-icon"></div>
                    <div class="freq-card-label">{{ $opt[1] }}</div>
                    <div class="freq-card-desc">{{ $opt[2] }}</div>
                </label>
                @endforeach
            </div>
        </div>

        <div class="form-card">
            <p class="form-section-title">Line Items</p>
            <div class="items-table-wrapper">
                <table class="items-table"><thead><tr><th style="min-width:200px;">Product / Service</th><th>Description</th><th style="width:80px;">Qty</th><th style="width:120px;">Unit Price</th><th style="width:120px;">Subtotal</th><th style="width:40px;"></th></tr></thead>
                <tbody id="itemsBody">
                    @php $existingItems = old('items') ? collect(old('items'))->map(fn($i)=>(object)$i) : $recurringInvoice->items; @endphp
                    @foreach($existingItems as $idx => $item)
                    <tr class="item-row">
                        <td data-label="Product / Service">
                            <select name="items[{{ $idx }}][product_id]" class="form-control form-control--sm product-select" onchange="fillProduct(this,{{ $idx }})">
                                <option value="">— Custom —</option>
                                @foreach($products as $p)
                                    <option value="{{ $p->id }}" data-price="{{ $p->selling_price }}" data-name="{{ $p->name }}"
                                        {{ ($item->product_id ?? '') == $p->id ? 'selected' : '' }}>
                                        {{ $p->name }} (KSh {{ number_format($p->selling_price,2) }})
                                    </option>
                                @endforeach
                            </select>
                            <input type="text" name="items[{{ $idx }}][product_name]" class="form-control form-control--sm product-name-input" style="margin-top:4px;" value="{{ $item->product_name ?? '' }}" required>
                        </td>
                        <td data-label="Description"><input type="text" name="items[{{ $idx }}][description]" class="form-control form-control--sm" value="{{ $item->description ?? '' }}"></td>
                        <td data-label="Qty"><input type="number" name="items[{{ $idx }}][quantity]" class="form-control form-control--sm qty-input" min="1" value="{{ $item->quantity ?? 1 }}" required onchange="updateRow(this)" oninput="updateRow(this)"></td>
                        <td data-label="Unit Price"><input type="number" name="items[{{ $idx }}][unit_price]" class="form-control form-control--sm price-input" min="0" step="0.01" value="{{ $item->unit_price ?? '' }}" required onchange="updateRow(this)" oninput="updateRow(this)"></td>
                        <td data-label="Subtotal"><input type="text" class="form-control form-control--sm subtotal-display" readonly value="{{ number_format(($item->unit_price ?? 0)*($item->quantity ?? 1),2) }}"></td>
                        <td><button type="button" class="remove-row-btn" onclick="removeRow(this)" title="Remove" aria-label="Remove">&times;</button></td>
                    </tr>
                    @endforeach
                </tbody></table>
            </div>
            <button type="button" class="add-item-btn" onclick="addRow()"> Add Line Item</button>
        </div>
    </div>

    <div>
        <div class="summary-panel">
            <h3 style="font-size:var(--text-base); font-weight:700; margin-bottom:var(--space-4); padding-bottom:var(--space-3); border-bottom:1px solid var(--color-border);">Summary</h3>
            <div class="form-group">
                <label class="form-label">Discount (KSh)</label>
                <input type="number" name="discount_amount" id="discount_amount" class="form-control" min="0" step="0.01" value="{{ old('discount_amount', $recurringInvoice->discount_amount) }}" oninput="updateTotals()">
            </div>
            <div class="form-group">
                <label class="form-label">Tax Rate (%)</label>
                <input type="number" name="tax_rate" id="tax_rate" class="form-control" min="0" max="100" step="0.01" value="{{ old('tax_rate', $recurringInvoice->tax_rate) }}" oninput="updateTotals()">
            </div>
            <div style="margin-top:var(--space-4);">
                <div class="s-row"><span>Subtotal</span><span id="subtotalDisplay">KSh 0.00</span></div>
                <div class="s-row"><span>Discount</span><span id="discountDisplay">KSh 0.00</span></div>
                <div class="s-row"><span>Tax</span><span id="taxDisplay">KSh 0.00</span></div>
                <div class="s-row total"><span>Total per Invoice</span><span id="totalDisplay">KSh 0.00</span></div>
            </div>
            <input type="hidden" name="subtotal" id="subtotalHidden">
            <input type="hidden" name="tax_amount" id="taxAmountHidden">
            <input type="hidden" name="total" id="totalHidden">
            <button type="submit" class="btn btn--primary" style="width:100%; margin-top:var(--space-5);">
                 Save Changes
            </button>
        </div>
    </div>
</div>
</form>
@endsection

@push('scripts')
<script>
let rowIndex = {{ $recurringInvoice->items->count() }};
function addRow(){
    const i=rowIndex++;
    const products=@json($products->map(fn($p)=>['id'=>$p->id,'name'=>$p->name,'price'=>$p->selling_price]));
    let opts='<option value="">— Custom —</option>';
    products.forEach(p=>{opts+=`<option value="${p.id}" data-price="${p.price}" data-name="${p.name}">${p.name} (KSh ${Number(p.price).toLocaleString('en-KE',{minimumFractionDigits:2})})</option>`;});
    document.getElementById('itemsBody').insertAdjacentHTML('beforeend',`<tr class="item-row"><td data-label="Product / Service"><select name="items[${i}][product_id]" class="form-control form-control--sm product-select" onchange="fillProduct(this,${i})">${opts}</select><input type="text" name="items[${i}][product_name]" class="form-control form-control--sm product-name-input" style="margin-top:4px;" required></td><td data-label="Description"><input type="text" name="items[${i}][description]" class="form-control form-control--sm"></td><td data-label="Qty"><input type="number" name="items[${i}][quantity]" class="form-control form-control--sm qty-input" min="1" value="1" required onchange="updateRow(this)" oninput="updateRow(this)"></td><td data-label="Unit Price"><input type="number" name="items[${i}][unit_price]" class="form-control form-control--sm price-input" min="0" step="0.01" required onchange="updateRow(this)" oninput="updateRow(this)"></td><td data-label="Subtotal"><input type="text" class="form-control form-control--sm subtotal-display" readonly value="0.00"></td><td><button type="button" class="remove-row-btn" onclick="removeRow(this)" title="Remove" aria-label="Remove">&times;</button></td></tr>`);
}
function fillProduct(sel,i){const opt=sel.options[sel.selectedIndex];const row=sel.closest('tr');if(opt.value){row.querySelector('.product-name-input').value=opt.dataset.name||'';row.querySelector('.price-input').value=opt.dataset.price||'';updateRow(row.querySelector('.qty-input'));}}
function updateRow(input){const row=input.closest('tr');const qty=parseFloat(row.querySelector('.qty-input').value)||0;const price=parseFloat(row.querySelector('.price-input').value)||0;row.querySelector('.subtotal-display').value=(qty*price).toFixed(2);updateTotals();}
function removeRow(btn){if(document.querySelectorAll('.item-row').length<=1){alert('At least one item is required.');return;}btn.closest('tr').remove();updateTotals();}
function updateTotals(){let s=0;document.querySelectorAll('.subtotal-display').forEach(el=>{s+=parseFloat(el.value)||0;});const d=parseFloat(document.getElementById('discount_amount').value)||0;const t=parseFloat(document.getElementById('tax_rate').value)||0;const tax=(s-d)*t/100;const total=s-d+tax;const fmt=n=>'KSh '+n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');document.getElementById('subtotalDisplay').textContent=fmt(s);document.getElementById('discountDisplay').textContent=fmt(d);document.getElementById('taxDisplay').textContent=fmt(tax);document.getElementById('totalDisplay').textContent=fmt(total);document.getElementById('subtotalHidden').value=s.toFixed(2);document.getElementById('taxAmountHidden').value=tax.toFixed(2);document.getElementById('totalHidden').value=total.toFixed(2);}
document.querySelectorAll('.freq-card').forEach(c=>{c.addEventListener('click',function(){document.querySelectorAll('.freq-card').forEach(x=>x.classList.remove('selected'));this.classList.add('selected');this.querySelector('input[type=radio]').checked=true;});});
document.addEventListener('DOMContentLoaded',updateTotals);
</script>
@endpush
