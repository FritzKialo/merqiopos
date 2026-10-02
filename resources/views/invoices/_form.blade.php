{{-- Shared invoice form partial --}}
@push('styles')
<style>
@media (max-width: 900px) {
    .invoice-form-grid-4 { grid-template-columns: 1fr 1fr !important; }
}
@media (max-width: 500px) {
    .invoice-form-grid-4, .invoice-form-grid-2 { grid-template-columns: 1fr !important; }
}
</style>
@endpush
<div class="table-card" style="margin-bottom:1.5rem;">
    <div class="card-body">
        <div class="invoice-form-grid-4" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:1rem;">
            <div class="form-group">
                <label class="form-label">Customer</label>
                <select name="customer_id" class="form-control">
                    <option value="">— No Customer —</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" {{ old('customer_id', $invoice?->customer_id) == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Issue Date *</label>
                <input type="date" name="issue_date" class="form-control" required
                       value="{{ old('issue_date', $invoice?->issue_date?->format('Y-m-d') ?? date('Y-m-d')) }}">
            </div>
            <div class="form-group">
                <label class="form-label">Due Date *</label>
                <input type="date" name="due_date" class="form-control" required
                       value="{{ old('due_date', $invoice?->due_date?->format('Y-m-d') ?? date('Y-m-d', strtotime('+30 days'))) }}">
            </div>
            <div class="form-group">
                <label class="form-label">Discount (KSh)</label>
                <input type="number" name="discount_amount" class="form-control" min="0" step="0.01"
                       value="{{ old('discount_amount', $invoice?->discount_amount ?? 0) }}" id="discountInput">
            </div>
        </div>
        <div class="invoice-form-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
                <label class="form-label">Payment Terms</label>
                <input type="text" name="payment_terms" class="form-control"
                       value="{{ old('payment_terms', $invoice?->payment_terms) }}" placeholder="e.g. Net 30">
            </div>
            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes', $invoice?->notes) }}</textarea>
            </div>
        </div>
    </div>
</div>

{{-- Line Items --}}
<div class="table-card" style="margin-bottom:1.5rem;">
    <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;">
        <h3>Line Items</h3>
        <button type="button" onclick="addInvRow()" class="btn btn--outline">+ Add Line</button>
    </div>
    <table class="table" id="invItemsTable">
        <thead>
            <tr>
                <th style="width:35%">Description *</th>
                <th style="width:10%">Qty *</th>
                <th style="width:15%">Unit Price *</th>
                <th style="width:10%">VAT %</th>
                <th style="width:12%">VAT Amt</th>
                <th style="width:12%">Subtotal</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="invItemsBody">
            @if($invoice && $invoice->items->count())
                @foreach($invoice->items as $i => $item)
                <tr>
                    <td data-label="Description"><input type="text" name="items[{{ $i }}][description]" class="form-control" value="{{ $item->description }}" required></td>
                    <td data-label="Qty"><input type="number" name="items[{{ $i }}][quantity]" class="form-control inv-qty" value="{{ $item->quantity }}" min="0.01" step="0.01" required></td>
                    <td data-label="Unit Price"><input type="number" name="items[{{ $i }}][unit_price]" class="form-control inv-price" value="{{ $item->unit_price }}" min="0" step="0.01" required></td>
                    <td data-label="VAT %"><input type="number" name="items[{{ $i }}][vat_rate]" class="form-control inv-vat" value="{{ $item->vat_rate }}" min="0" max="100" step="0.01"></td>
                    <td data-label="VAT Amt" class="inv-vat-amt">KSh 0</td>
                    <td data-label="Subtotal" class="inv-sub">KSh 0</td>
                    <td data-label=""><button type="button" onclick="removeInvRow(this)" class="btn btn--danger btn--sm">✕</button></td>
                </tr>
                @endforeach
            @else
                <tr>
                    <td data-label="Description"><input type="text" name="items[0][description]" class="form-control" required placeholder="Description"></td>
                    <td data-label="Qty"><input type="number" name="items[0][quantity]" class="form-control inv-qty" value="1" min="0.01" step="0.01" required></td>
                    <td data-label="Unit Price"><input type="number" name="items[0][unit_price]" class="form-control inv-price" value="0" min="0" step="0.01" required></td>
                    <td data-label="VAT %"><input type="number" name="items[0][vat_rate]" class="form-control inv-vat" value="{{ $business->vatRate() }}" min="0" max="100" step="0.01"></td>
                    <td data-label="VAT Amt" class="inv-vat-amt">KSh 0</td>
                    <td data-label="Subtotal" class="inv-sub">KSh 0</td>
                    <td data-label=""><button type="button" onclick="removeInvRow(this)" class="btn btn--danger btn--sm">✕</button></td>
                </tr>
            @endif
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="text-align:right;padding:0.75rem;">Subtotal</td>
                <td id="totalVat" style="padding:0.75rem;">KSh 0</td>
                <td id="totalSub" style="padding:0.75rem;">KSh 0</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="5" style="text-align:right;padding:0.75rem;">Discount</td>
                <td id="discountDisplay" style="padding:0.75rem;">KSh 0</td>
                <td></td>
            </tr>
            <tr style="font-weight:700;">
                <td colspan="5" style="text-align:right;padding:0.75rem;">Total</td>
                <td id="grandInvTotal" style="padding:0.75rem;">KSh 0</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<script>
const invProducts = @json($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'price' => $p->selling_price]));
const defaultVat = {{ $business->vatRate() }};
let invRowIdx = {{ $invoice && $invoice->items->count() ? $invoice->items->count() : 1 }};

function addInvRow() {
    const i = invRowIdx++;
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td data-label="Description"><input type="text" name="items[${i}][description]" class="form-control" required placeholder="Description"></td>
        <td data-label="Qty"><input type="number" name="items[${i}][quantity]" class="form-control inv-qty" value="1" min="0.01" step="0.01" required></td>
        <td data-label="Unit Price"><input type="number" name="items[${i}][unit_price]" class="form-control inv-price" value="0" min="0" step="0.01" required></td>
        <td data-label="VAT %"><input type="number" name="items[${i}][vat_rate]" class="form-control inv-vat" value="${defaultVat}" min="0" max="100" step="0.01"></td>
        <td data-label="VAT Amt" class="inv-vat-amt">KSh 0</td>
        <td data-label="Subtotal" class="inv-sub">KSh 0</td>
        <td data-label=""><button type="button" onclick="removeInvRow(this)" class="btn btn--danger btn--sm">✕</button></td>`;
    document.getElementById('invItemsBody').appendChild(tr);
    addInvListeners(tr);
    updateInvTotals();
}

function removeInvRow(btn) { btn.closest('tr').remove(); updateInvTotals(); }

function updateInvTotals() {
    let sub = 0, vat = 0;
    document.querySelectorAll('#invItemsBody tr').forEach(tr => {
        const qty   = parseFloat(tr.querySelector('.inv-qty')?.value || 0);
        const price = parseFloat(tr.querySelector('.inv-price')?.value || 0);
        const vatR  = parseFloat(tr.querySelector('.inv-vat')?.value || 0);
        const lineSub = qty * price;
        const lineVat = lineSub * vatR / 100;
        sub += lineSub; vat += lineVat;
        tr.querySelector('.inv-sub').textContent = 'KSh ' + Math.round(lineSub).toLocaleString();
        tr.querySelector('.inv-vat-amt').textContent = 'KSh ' + Math.round(lineVat).toLocaleString();
    });
    const disc = parseFloat(document.getElementById('discountInput')?.value || 0);
    const total = sub + vat - disc;
    document.getElementById('totalSub').textContent = 'KSh ' + Math.round(sub).toLocaleString();
    document.getElementById('totalVat').textContent = 'KSh ' + Math.round(vat).toLocaleString();
    document.getElementById('discountDisplay').textContent = '- KSh ' + Math.round(disc).toLocaleString();
    document.getElementById('grandInvTotal').textContent = 'KSh ' + Math.round(total).toLocaleString();
}

function addInvListeners(tr) {
    tr.querySelectorAll('.inv-qty,.inv-price,.inv-vat').forEach(i => i.addEventListener('input', updateInvTotals));
}

document.querySelectorAll('#invItemsBody tr').forEach(addInvListeners);
document.getElementById('discountInput')?.addEventListener('input', updateInvTotals);
updateInvTotals();
</script>
