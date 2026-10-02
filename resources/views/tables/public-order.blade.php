<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Order — Table {{ $table->name ?: $table->number }} — {{ $business->name }}</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background: #f4f4f5; color: #18181b; margin: 0; padding: 0 0 100px; }
    .header { background: {{ $business->receipt_color ?: '#111827' }}; color: #fff; padding: 20px 16px 16px; }
    .header h1 { margin: 0; font-size: 1.15rem; }
    .header p { margin: 4px 0 0; font-size: .82rem; opacity: .85; }
    .wrap { max-width: 560px; margin: 0 auto; padding: 0 16px; }
    .flash { margin: 12px 0; padding: 10px 14px; border-radius: 8px; font-size: .88rem; }
    .flash-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
    .flash-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .product { display: flex; justify-content: space-between; align-items: center; gap: 10px; background: #fff; border: 1px solid #e4e4e7; border-radius: 10px; padding: 12px 14px; margin-top: 10px; }
    .product-name { font-weight: 600; font-size: .95rem; }
    .product-price { font-size: .82rem; color: #71717a; margin-top: 2px; }
    .add-btn { background: {{ $business->receipt_color ?: '#111827' }}; color: #fff; border: none; border-radius: 8px; padding: 8px 16px; font-weight: 600; cursor: pointer; flex-shrink: 0; }
    .section-title { margin: 20px 0 4px; font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; color: #71717a; font-weight: 700; }
    .req { display: flex; justify-content: space-between; background: #fff; border: 1px solid #e4e4e7; border-radius: 8px; padding: 10px 14px; margin-top: 8px; font-size: .85rem; }
    .req-status { font-size: .72rem; font-weight: 700; padding: 2px 8px; border-radius: 20px; }
    .req-status.pending  { background: #fef3c7; color: #92400e; }
    .req-status.approved { background: #dcfce7; color: #166534; }
    .req-status.rejected { background: #f3f4f6; color: #52525b; }
    .modal-bg { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); align-items: flex-end; justify-content: center; z-index: 20; }
    .modal-bg.open { display: flex; }
    .modal { background: #fff; width: 100%; max-width: 560px; border-radius: 16px 16px 0 0; padding: 20px 16px calc(20px + env(safe-area-inset-bottom)); }
    .modal h3 { margin: 0 0 12px; }
    .modal label { display: block; font-size: .82rem; font-weight: 600; margin: 12px 0 4px; }
    .modal input, .modal textarea { width: 100%; padding: 10px 12px; border: 1px solid #d4d4d8; border-radius: 8px; font-size: 1rem; }
    .modal .row { display: flex; gap: 10px; margin-top: 16px; }
    .modal .row button { flex: 1; padding: 12px; border-radius: 8px; font-weight: 700; border: none; font-size: .95rem; }
    .btn-cancel { background: #f4f4f5; color: #18181b; }
    .btn-submit { background: {{ $business->receipt_color ?: '#111827' }}; color: #fff; }
    .empty { text-align: center; color: #71717a; padding: 30px 0; font-size: .9rem; }
</style>
</head>
<body>

<div class="header">
    <h1>{{ $business->name }}</h1>
    <p>Table {{ $table->name ?: $table->number }} — order from your phone; staff will confirm it.</p>
</div>

<div class="wrap">
    @if(session('success'))<div class="flash flash-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="flash flash-error">{{ $errors->first() }}</div>@endif

    @if($myRequests->isNotEmpty())
    <div class="section-title">Your requests</div>
    @foreach($myRequests as $req)
    <div class="req">
        <span>{{ (float) $req->quantity == (int) $req->quantity ? (int) $req->quantity : $req->quantity }}× {{ $req->product_name }}</span>
        <span class="req-status {{ $req->status }}">{{ ucfirst($req->status) }}</span>
    </div>
    @endforeach
    @endif

    <div class="section-title">Menu</div>
    @forelse($products as $product)
    <div class="product">
        <div>
            <div class="product-name">{{ $product->name }}</div>
            <div class="product-price">KSh {{ number_format($product->selling_price, 0) }}</div>
        </div>
        <button type="button" class="add-btn" onclick="openModal({{ $product->id }}, {{ Js::from($product->name) }})">Add</button>
    </div>
    @empty
    <div class="empty">Nothing on the menu right now — please ask a member of staff.</div>
    @endforelse
</div>

<div class="modal-bg" id="modalBg">
    <div class="modal">
        <h3 id="modalTitle">Add item</h3>
        <form method="POST" action="{{ route('table-order.public.store', $table->qr_token) }}">
            @csrf
            <input type="hidden" name="product_id" id="modalProductId">
            <label for="modalQty">Quantity</label>
            <input type="number" name="quantity" id="modalQty" value="1" min="1" max="50" step="1" required>
            <label for="modalNotes">Note for the kitchen (optional)</label>
            <textarea name="notes" id="modalNotes" maxlength="255" rows="2" placeholder="e.g. no onions"></textarea>
            <div class="row">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-submit">Send request</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id, name) {
    document.getElementById('modalProductId').value = id;
    document.getElementById('modalTitle').textContent = 'Add ' + name;
    document.getElementById('modalBg').classList.add('open');
}
function closeModal() { document.getElementById('modalBg').classList.remove('open'); }
document.getElementById('modalBg').addEventListener('click', function (e) { if (e.target === this) closeModal(); });
</script>
</body>
</html>
