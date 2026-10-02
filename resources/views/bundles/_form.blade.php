@php $b = $bundle ?? null; @endphp

<div class="form-grid-2">
    <div class="form-group">
        <label class="form-label">Bundle Name *</label>
        <input type="text" name="name" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
            value="{{ old('name', $b?->name) }}" required>
        @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
    </div>
    <div class="form-group">
        <label class="form-label">SKU</label>
        <input type="text" name="sku" class="form-control"
            value="{{ old('sku', $b?->sku) }}" placeholder="Optional">
    </div>
    <div class="form-group">
        <label class="form-label">Bundle Price (KSh) *</label>
        <input type="number" name="price" class="form-control {{ $errors->has('price') ? 'is-invalid' : '' }}"
            value="{{ old('price', $b?->price) }}" min="0" step="0.01" required>
        @error('price')<span class="invalid-feedback">{{ $message }}</span>@enderror
    </div>
    <div class="form-group">
        <label class="form-label" style="display:flex; align-items:center; gap:8px; cursor:pointer; margin-top:24px;">
            <input type="checkbox" name="is_active" value="1"
                {{ old('is_active', $b ? $b->is_active : true) ? 'checked' : '' }}>
            Active
        </label>
    </div>
</div>

<div class="form-group">
    <label class="form-label">Description</label>
    <textarea name="description" class="form-control" rows="2">{{ old('description', $b?->description) }}</textarea>
</div>

<hr style="margin:var(--space-lg) 0; border-color:var(--color-border);">
<h3 style="margin:0 0 var(--space-md); font-size:var(--text-base);">Bundle Components</h3>

<div id="bundleItems">
    @forelse($b?->items ?? [] as $item)
    <div class="bundle-row" style="display:flex; gap:8px; margin-bottom:8px; align-items:center; flex-wrap:wrap;">
        <select name="items[{{ $loop->index }}][product_id]" class="form-control" style="flex:2; min-width:160px;" required>
            <option value="">— Select Product —</option>
            @foreach($products as $p)
                <option value="{{ $p->id }}" {{ $item->product_id == $p->id ? 'selected' : '' }}>
                    {{ $p->name }} (KSh {{ number_format($p->selling_price, 2) }})
                </option>
            @endforeach
        </select>
        <input type="number" name="items[{{ $loop->index }}][quantity]" class="form-control"
            style="width:80px;" value="{{ $item->quantity }}" min="1" step="0.01" placeholder="Qty">
        <button type="button" class="btn btn-danger" style="padding:6px 10px; white-space:nowrap;"
            onclick="this.parentElement.remove()">&#10005;</button>
    </div>
    @empty
    <div class="bundle-row" style="display:flex; gap:8px; margin-bottom:8px; align-items:center; flex-wrap:wrap;">
        <select name="items[0][product_id]" class="form-control" style="flex:2; min-width:160px;" required>
            <option value="">— Select Product —</option>
            @foreach($products as $p)
                <option value="{{ $p->id }}">{{ $p->name }} (KSh {{ number_format($p->selling_price, 2) }})</option>
            @endforeach
        </select>
        <input type="number" name="items[0][quantity]" class="form-control"
            style="width:80px;" value="1" min="1" step="0.01" placeholder="Qty">
        <button type="button" class="btn btn-danger" style="padding:6px 10px;"
            onclick="this.parentElement.remove()">&#10005;</button>
    </div>
    @endforelse
</div>

<button type="button" class="btn btn-outline" style="margin-top:8px;" onclick="addBundleRow()">+ Add Component</button>

@push('scripts')
<script>
var bundleRowCount = {{ ($b?->items->count() ?? 1) }};
var PRODUCTS_LIST  = @json($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'price' => $p->selling_price]));

function addBundleRow() {
    var idx  = bundleRowCount++;
    var opts = PRODUCTS_LIST.map(p => `<option value="${p.id}">${p.name} (KSh ${parseFloat(p.price).toFixed(2)})</option>`).join('');
    var html = `<div class="bundle-row" style="display:flex; gap:8px; margin-bottom:8px; align-items:center; flex-wrap:wrap;">
        <select name="items[${idx}][product_id]" class="form-control" style="flex:2; min-width:160px;" required>
            <option value="">— Select Product —</option>${opts}
        </select>
        <input type="number" name="items[${idx}][quantity]" class="form-control"
            style="width:80px;" value="1" min="1" step="0.01" placeholder="Qty">
        <button type="button" class="btn btn-danger" style="padding:6px 10px;"
            onclick="this.parentElement.remove()">&#10005;</button>
    </div>`;
    document.getElementById('bundleItems').insertAdjacentHTML('beforeend', html);
}
</script>
@endpush
