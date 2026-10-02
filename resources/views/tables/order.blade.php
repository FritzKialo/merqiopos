@extends('layouts.app')
@section('title', 'Table ' . ($order->table->name ?: $order->table->number) . ' — Order')
@push('styles')
<style>
@media (max-width: 900px) {
    .table-order-layout { grid-template-columns: 1fr !important; }
}
@media (min-width: 769px) {
    .table-order-items th, .table-order-items td { padding: 6px 8px; }
}

/* Tablets and larger phones (481–900px): keep the order as a real compact table.
   The generic phone layout stacks every cell onto its own labelled line — six lines
   for one dish, so a busy table ran to several screens. */
@media (min-width: 481px) and (max-width: 900px) {
    .table-order-items,
    .table-order-items thead,
    .table-order-items tbody,
    .table-order-items tfoot,
    .table-order-items tr,
    .table-order-items th,
    .table-order-items td { display: revert; }
    .table-order-items { width: 100%; }
    .table-order-items td, .table-order-items th { padding: 10px 6px !important; text-align: revert; min-height: revert; }
    .table-order-items td::before { content: none !important; }
    .table-order-items th { text-align: left; }
    /* the phone layout also throws the header row far off-screen */
    .table-order-items thead tr { position: static; top: auto; left: auto; visibility: visible; }
    .table-order-items tbody tr { padding: 0; border-bottom: 1px solid var(--color-border); }
}

/* Below desktop size everything is tapped with a finger, not clicked: a fingertip needs
   roughly 44px. The tick boxes, x buttons, Save and Move were about 13-31px. */
@media (max-width: 900px) {
    .table-order-layout .btn,
    .table-order-layout button { min-height: 44px; }
    .table-order-layout .btn-sm { padding-left: 14px; padding-right: 14px; }
    .table-order-layout .form-control { min-height: 44px; }
    .table-order-layout input.pay-item { width: 26px; height: 26px; }
    .table-order-layout table button.btn-danger { min-width: 44px; }
}
</style>
@endpush
@section('content')
<div class="page-header">
    <h1>Table {{ ($order->table->name ?: $order->table->number) }}
        @if($order->status === 'billed')<span class="badge badge-warning" style="font-size:.7rem;vertical-align:middle;">Billed — awaiting payment</span>@endif
    </h1>
    <a href="{{ route('tables.floor') }}" class="btn btn-secondary">← Floor Plan</a>
</div>

<div class="table-order-layout" style="display:grid; grid-template-columns:2fr 1fr; gap:24px;">
    <div>
        <div class="card" style="margin-bottom:16px;">
            <div class="card-body">
                <h3 style="margin:0 0 16px;">Add Items</h3>
                <form method="POST" action="{{ route('tables.orders.items.add', $order) }}">
                    @csrf
                    <div style="display:flex; gap:8px; margin-bottom:12px; flex-wrap:wrap;">
                        <input type="text" id="product-search" class="form-control" placeholder="Search product..." style="flex:1; min-width:160px;" autocomplete="off">
                        <input type="number" name="quantity" value="1" min="1" step="1" class="form-control" style="width:80px;">
                        <input type="hidden" name="product_id" id="product-id">
                        <button type="submit" class="btn btn-primary">Add</button>
                    </div>
                    <input type="text" name="notes" class="form-control" maxlength="255" placeholder="Note for the kitchen (optional) — e.g. no onions, well done" style="margin-bottom:12px;">
                    <div id="product-results" style="border:1px solid var(--color-border); border-radius:6px; max-height:200px; overflow-y:auto; display:none;"></div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 style="margin:0 0 16px;">Current Order</h3>
                @if(!$order || $order->items->isEmpty())
                <p class="text-muted" style="font-size:0.9rem;">No items yet.</p>
                @else
                <table class="table-order-items" style="width:100%; border-collapse:collapse;">
                    <thead><tr style="border-bottom:2px solid var(--color-text);">
                        <th style="width:34px; font-size:0.75rem;" title="Tick the items this payment covers">Pay</th>
                        <th style="text-align:left; font-size:0.85rem;">Item</th>
                        <th style="text-align:right; font-size:0.85rem;">Qty</th>
                        <th style="text-align:right; font-size:0.85rem;">Price</th>
                        <th style="text-align:right; font-size:0.85rem;">Total</th>
                        <th></th>
                    </tr></thead>
                    <tbody>
                        @foreach($order->items as $item)
                        @php $isPaid = (bool) $item->sale_id; @endphp
                        <tr style="border-bottom:1px solid var(--color-border);{{ $isPaid ? 'opacity:.55;' : '' }}">
                            <td data-label="Pay">
                                @if(!$isPaid && $item->status !== 'cancelled')
                                <input type="checkbox" form="payForm" name="item_ids[]" value="{{ $item->id }}" class="pay-item" data-total="{{ (float) $item->total }}" checked>
                                @endif
                            </td>
                            <td data-label="Item">{{ $item->product_name }}
                                @if($isPaid)<div style="font-size:.72rem;color:#16a34a;">✓ paid — {{ $paidSales[$item->sale_id] ?? 'sale' }}</div>@endif
                                @if($item->notes)<div style="font-size:.78rem;color:var(--color-text-muted);">» {{ $item->notes }}</div>@endif
                                @if($item->sent_to_kitchen_at)<div style="font-size:.72rem;color:#16a34a;">✓ sent to kitchen {{ $item->sent_to_kitchen_at->format('H:i') }}</div>@endif
                            </td>
                            <td data-label="Qty" style="text-align:right;">{{ $item->quantity }}</td>
                            <td data-label="Price" style="text-align:right;">KSh {{ number_format($item->unit_price, 0) }}</td>
                            <td data-label="Total" style="text-align:right;">
                                KSh {{ number_format($item->total, 0) }}
                                @if($item->discount > 0)<div style="font-size:.72rem;color:#16a34a;">−KSh {{ number_format($item->discount, 0) }} off</div>@endif
                            </td>
                            <td data-label="Actions">
                                @unless($isPaid)
                                <div style="display:flex;gap:4px;align-items:center;justify-content:flex-end;">
                                    <button type="button" class="btn btn-secondary btn-sm item-discount-btn"
                                        data-item="{{ $item->id }}" data-gross="{{ (float) $item->unit_price * (float) $item->quantity }}"
                                        data-discount="{{ (float) $item->discount }}" title="Discount this item">%</button>
                                    <form method="POST" action="{{ route('tables.orders.items.remove', [$order, $item]) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">×</button>
                                    </form>
                                </div>
                                <form method="POST" action="{{ route('tables.orders.items.discount', [$order, $item]) }}" id="discountForm{{ $item->id }}" style="display:none;">
                                    @csrf
                                    <input type="hidden" name="discount" id="discountInput{{ $item->id }}">
                                </form>
                                @endunless
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        @if($order->service_charge_amount > 0)
                        <tr>
                            <td colspan="4" style="text-align:right;font-size:.85rem;">Service charge ({{ rtrim(rtrim(number_format($order->service_charge_percent, 2), '0'), '.') }}%)</td>
                            <td style="text-align:right;font-size:.85rem;">KSh {{ number_format($order->service_charge_amount, 2) }}</td>
                            <td></td>
                        </tr>
                        @endif
                        <tr style="border-top:2px solid var(--color-text); font-weight:bold;">
                            <td data-label="Total" colspan="4" style="text-align:right;">Still to pay</td>
                            <td data-label="Amount" style="text-align:right;">KSh {{ number_format($order->total, 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                @endif
            </div>
        </div>
    </div>

    <div>
        @if($order && !$order->items->isEmpty())
        @php $unsent = $order->items->where('status', '!=', 'cancelled')->whereNull('sent_to_kitchen_at')->count(); @endphp
        <div class="card" style="margin-bottom:16px;">
            <div class="card-body">
                <h3 style="margin:0 0 12px;">Table &amp; Service</h3>
                <form method="POST" action="{{ route('tables.orders.service-charge', $order) }}" style="display:flex;gap:6px;align-items:end;margin-bottom:12px;">
                    @csrf
                    <div style="flex:1;">
                        <label class="form-label" style="font-size:.8rem;">Service charge %</label>
                        <input type="number" name="service_charge_percent" class="form-control" min="0" max="30" step="0.5" value="{{ (float) $order->service_charge_percent }}">
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                </form>
                @if($freeTables->isNotEmpty())
                <form method="POST" action="{{ route('tables.orders.move', $order) }}" style="display:flex;gap:6px;align-items:end;">
                    @csrf
                    <div style="flex:1;">
                        <label class="form-label" style="font-size:.8rem;">Move this order to</label>
                        <select name="to_table_id" class="form-control">
                            @foreach($freeTables as $ft)
                            <option value="{{ $ft->id }}">Table {{ $ft->name ?: $ft->number }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm">Move</button>
                </form>
                @else
                <div class="text-muted" style="font-size:.8rem;">No free table to move this order to.</div>
                @endif
                @if($mergeableTables->isNotEmpty())
                <form method="POST" action="{{ route('tables.orders.merge', $order) }}" style="display:flex;gap:6px;align-items:end;margin-top:12px;" onsubmit="return confirm('Bring that table\'s order onto this one? The other table will be freed.')">
                    @csrf
                    <div style="flex:1;">
                        <label class="form-label" style="font-size:.8rem;">Merge in another table's order</label>
                        <select name="from_table_id" class="form-control">
                            @foreach($mergeableTables as $mt)
                            <option value="{{ $mt->id }}">Table {{ $mt->name ?: $mt->number }} (KSh {{ number_format($mt->currentOrder->total, 0) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm">Merge</button>
                </form>
                @endif
                <a href="{{ route('tables.qr', $order->table) }}" class="btn btn-secondary btn-sm" style="display:inline-block;margin-top:12px;">📱 QR order code for this table</a>
            </div>
        </div>

        <div class="card" style="margin-bottom:16px;">
            <div class="card-body">
                <h3 style="margin:0 0 12px;">Kitchen &amp; Bill</h3>
                <form method="POST" action="{{ route('tables.orders.kitchen', $order) }}" style="margin-bottom:8px;">
                    @csrf
                    <button type="submit" class="btn btn-secondary" style="width:100%;" {{ $unsent ? '' : 'disabled' }}>
                        Send to kitchen{{ $unsent ? ' (' . $unsent . ' new)' : ' — all sent' }}
                    </button>
                </form>
                <form method="POST" action="{{ route('tables.orders.bill', $order) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-secondary" style="width:100%;">{{ $order->status === 'billed' ? 'Reprint bill' : 'Print bill' }}</button>
                </form>
                <div class="text-muted" style="font-size:.78rem;margin-top:8px;">The bill is for the customer to check before paying. The receipt is printed after payment.</div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 style="margin:0 0 4px;">Checkout</h3>
                <div style="margin-bottom:14px;font-size:.85rem;color:var(--color-text-muted);">
                    Paying now: <strong id="payingNow" style="color:var(--color-text);">KSh {{ number_format($order->total, 2) }}</strong>
                    <span id="payingHint" style="display:block;">Untick items to let each guest pay for their own.</span>
                </div>
                <form method="POST" action="{{ route('tables.orders.pay', $order) }}" id="payForm">
                    @csrf
                    <div style="margin-bottom:12px;">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" id="payMethod" class="form-control">
                            <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Cash</option>
                            <option value="mpesa" {{ old('payment_method') === 'mpesa' ? 'selected' : '' }}>M-Pesa</option>
                            <option value="card" {{ old('payment_method') === 'card' ? 'selected' : '' }}>Card</option>
                            <option value="bank_transfer" {{ old('payment_method') === 'bank_transfer' ? 'selected' : '' }}>Bank transfer</option>
                        </select>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label class="form-label">Tip (optional)</label>
                        <input type="number" name="tip_amount" id="tipAmount" class="form-control" min="0" step="0.01" value="{{ old('tip_amount') }}" placeholder="0">
                        <div class="text-muted" style="font-size:.75rem;">Recorded on the receipt, not part of the sale.</div>
                    </div>
                    <div id="cashFields" style="margin-bottom:12px;">
                        <label class="form-label">Cash received</label>
                        <div style="display:flex;gap:6px;">
                            <input type="number" name="amount_tendered" id="tendered" class="form-control" min="0" step="0.01" value="{{ old('amount_tendered') }}" placeholder="Amount handed over">
                            <button type="button" class="btn btn-secondary btn-sm" id="exactBtn">Exact</button>
                        </div>
                        <div id="changeBox" style="margin-top:6px;font-weight:700;"></div>
                    </div>
                    <div id="mpesaFields" style="margin-bottom:12px;display:none;">
                        <label class="form-label">M-Pesa code (optional)</label>
                        <input type="text" name="mpesa_reference" class="form-control" maxlength="50" value="{{ old('mpesa_reference') }}" placeholder="e.g. TKL1A2B3C4" style="text-transform:uppercase;">
                    </div>
                    <div style="margin-bottom:16px;">
                        <label class="form-label">Customer name (optional)</label>
                        <input type="text" name="customer_name" class="form-control" maxlength="100" value="{{ old('customer_name', $order->customer_name) }}" placeholder="Walk-in">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">Pay selected &amp; Print Receipt</button>
                    <div style="margin-top:12px;border-top:1px dashed var(--color-border);padding-top:10px;font-size:.85rem;">
                        <label for="splitN" class="form-label" style="font-size:.8rem;">Just splitting the total equally?</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="number" id="splitN" min="2" max="20" value="2" class="form-control" style="width:70px;">
                            <span>guests → <strong id="splitEach">—</strong> each</span>
                        </div>
                        <div class="text-muted" style="font-size:.72rem;margin-top:4px;">A calculator only. To record each guest's own payment, untick the others' items and pay one guest at a time.</div>
                    </div>
                </form>
                <form method="POST" action="{{ route('tables.orders.clear', $order) }}" style="margin-top:8px;" onsubmit="return confirm('Clear all items?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger" style="width:100%;">Clear Order</button>
                </form>
            </div>
        </div>
        @endif

        {{-- Unconditional (unlike the panels above): a customer can send a request before
             this order has a single item on it yet, right after staff opens the table. --}}
        @if($pendingRequests->isNotEmpty())
        <div class="card" style="margin-top:16px;border-color:#f59e0b;">
            <div class="card-body">
                <h3 style="margin:0 0 12px;">Customer requests ({{ $pendingRequests->count() }})</h3>
                @foreach($pendingRequests as $req)
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid var(--color-border);">
                    <div style="font-size:.88rem;">
                        {{ (float) $req->quantity == (int) $req->quantity ? (int) $req->quantity : $req->quantity }}× {{ $req->product_name }}
                        @if($req->notes)<div style="font-size:.75rem;color:var(--color-text-muted);">» {{ $req->notes }}</div>@endif
                    </div>
                    <div style="display:flex;gap:6px;flex-shrink:0;">
                        <form method="POST" action="{{ route('tables.requests.approve', [$order->table, $req]) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm">Add</button>
                        </form>
                        <form method="POST" action="{{ route('tables.requests.reject', [$order->table, $req]) }}">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-sm">Dismiss</button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="card" style="margin-top:16px;">
            <div class="card-body">
                <div class="text-muted" style="font-size:0.85rem;">Table Status</div>
                <div style="font-weight:bold; margin:4px 0;">{{ ($order->table->name ?: $order->table->number) }} — {{ ucfirst($order->table->status) }}</div>
                <div class="text-muted" style="font-size:0.85rem;">Capacity: {{ $order->table->capacity }} seats</div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const searchInput = document.getElementById('product-search');
const results = document.getElementById('product-results');
const productId = document.getElementById('product-id');
let debounce;
searchInput.addEventListener('input', function() {
    clearTimeout(debounce);
    if (!this.value.trim()) { results.style.display='none'; return; }
    debounce = setTimeout(() => {
        fetch('/api/products/search?q=' + encodeURIComponent(this.value) + '&business_id={{ auth()->user()->currentBusiness()->id ?? "" }}')
            .then(r => r.json()).then(data => {
                if (!data.length) { results.style.display='none'; return; }
                results.innerHTML = data.map(p => `<div onclick="selectProduct(${p.id},'${p.name.replace(/'/g,"\\'")}',${p.selling_price})" style="padding:10px 12px; cursor:pointer; border-bottom:1px solid var(--color-border); font-size:0.9rem; display:flex; justify-content:space-between;"><span>${p.name}</span><span style="color:var(--color-text-muted);">KSh ${parseInt(p.selling_price).toLocaleString()}</span></div>`).join('');
                results.style.display='block';
            });
    }, 300);
});
function selectProduct(id, name, price) {
    productId.value = id;
    searchInput.value = name;
    results.style.display='none';
}
document.addEventListener('click', e => { if (!results.contains(e.target) && e.target !== searchInput) results.style.display='none'; });

// Per-item discount: a flat KSh amount, prompted inline rather than a whole extra row.
document.querySelectorAll('.item-discount-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const id = btn.dataset.item, gross = parseFloat(btn.dataset.gross), current = parseFloat(btn.dataset.discount) || 0;
        const value = prompt('Discount for this item (KSh, up to ' + gross.toFixed(2) + '):', current || '0');
        if (value === null) return;
        const n = parseFloat(value);
        if (isNaN(n) || n < 0 || n > gross) { alert('Enter an amount between 0 and ' + gross.toFixed(2) + '.'); return; }
        document.getElementById('discountInput' + id).value = n;
        document.getElementById('discountForm' + id).submit();
    });
});

// Checkout: show the fields that belong to the chosen payment method, and the change to hand back.
(function () {
    const method = document.getElementById('payMethod');
    if (!method) return;
    const pct = {{ (float) $order->service_charge_percent }};
    const tendered = document.getElementById('tendered');
    const tip = document.getElementById('tipAmount');
    const payingNow = document.getElementById('payingNow');
    const splitN = document.getElementById('splitN');
    const splitEach = document.getElementById('splitEach');
    // What this payment covers: the ticked items plus the service charge on them.
    function currentTotal() {
        let sub = 0;
        document.querySelectorAll('.pay-item:checked').forEach(function (c) { sub += parseFloat(c.dataset.total) || 0; });
        return Math.round((sub + sub * pct / 100) * 100) / 100;
    }
    let total = currentTotal();
    const changeBox = document.getElementById('changeBox');
    const cashFields = document.getElementById('cashFields');
    const mpesaFields = document.getElementById('mpesaFields');
    function refresh() {
        cashFields.style.display = method.value === 'cash' ? '' : 'none';
        mpesaFields.style.display = method.value === 'mpesa' ? '' : 'none';
        if (method.value !== 'cash') { changeBox.textContent = ''; return; }
        const v = parseFloat(tendered.value);
        if (isNaN(v)) { changeBox.textContent = ''; return; }
        const diff = v - total - (parseFloat(tip.value) || 0);
        changeBox.style.color = diff < 0 ? '#c00' : '#166534';
        changeBox.textContent = diff < 0 ? 'Short by KSh ' + Math.abs(diff).toFixed(2) : 'Change: KSh ' + diff.toFixed(2);
    }
    function recompute() {
        total = currentTotal();
        payingNow.textContent = 'KSh ' + total.toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const n = Math.max(2, parseInt(splitN.value) || 2);
        splitEach.textContent = 'KSh ' + (Math.ceil(total / n * 100) / 100).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        refresh();
    }
    method.addEventListener('change', refresh);
    tendered.addEventListener('input', refresh);
    tip.addEventListener('input', refresh);
    splitN.addEventListener('input', recompute);
    document.querySelectorAll('.pay-item').forEach(function (c) { c.addEventListener('change', recompute); });
    document.getElementById('exactBtn').addEventListener('click', function () { tendered.value = (total + (parseFloat(tip.value) || 0)).toFixed(2); refresh(); });
    recompute();
})();
</script>
@endpush
@endsection
