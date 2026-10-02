@extends('layouts.app')
@section('title', 'Edit Product')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/inventory.css') }}?v={{ @filemtime(public_path('css/inventory.css')) ?: '1' }}">
@endpush

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">&#9998; Edit Product</h1>
        <p class="page-subtitle">{{ $product->name }}</p>
    </div>
    <div style="display:flex; gap:8px;">
        <a href="{{ route('inventory.show', $product) }}" class="btn btn-outline">View Product</a>
        <a href="{{ route('inventory.index') }}" class="btn btn-outline">&#8592; Back</a>
    </div>
</div>

<div class="form-card" style="max-width:780px;">
    <form method="POST" action="{{ route('inventory.update', $product) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        {{-- Row 1: Product Name --}}
        <div class="form-group">
            <label class="form-label" for="name">Product Name *</label>
            <input type="text" id="name" name="name"
                   class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                   value="{{ old('name', $product->name) }}"
                   required autofocus>
            @error('name')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        {{-- Row 2: SKU + Category --}}
        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label" for="sku">SKU</label>
                <input type="text" id="sku" name="sku"
                       class="form-control {{ $errors->has('sku') ? 'is-invalid' : '' }}"
                       value="{{ old('sku', $product->sku) }}">
                @error('sku')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="category_id">Category</label>
                <select id="category_id" name="category_id" class="form-control">
                    <option value="">— Select Category —</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}"
                            data-children="{{ $cat->children->pluck('name','id')->toJson() }}"
                            {{ old('category_id', $selectedTopCategoryId) == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Row 2b: Sub-category + Brand --}}
        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label" for="sub_category_id">Sub-Category</label>
                <select id="sub_category_id" name="sub_category_id" class="form-control">
                    <option value="">— None —</option>
                </select>
                <span class="form-hint">Options depend on the Category chosen above</span>
            </div>
            <div class="form-group">
                <label class="form-label" for="brand">Brand</label>
                <input type="text" id="brand" name="brand" class="form-control"
                       value="{{ old('brand', $product->brand) }}" placeholder="e.g. Coca-Cola">
            </div>
        </div>

        {{-- Row 3: Barcode (feature-gated) --}}
        @if(auth()->user()->currentBusiness()?->hasFeature('barcode'))
        <div class="form-group">
            <label class="form-label" for="barcode">Barcode</label>
            <div style="display:flex; gap:var(--space-2);">
                <input type="text" id="barcode" name="barcode" class="form-control"
                       value="{{ old('barcode', $product->barcode) }}"
                       placeholder="Scan or type barcode (EAN, UPC, QR...)">
                <select id="barcode_symbology" name="barcode_symbology" class="form-control" style="max-width:140px;">
                    @foreach(['CODE128','EAN13','EAN8','UPC','CODE39'] as $sym)
                        <option value="{{ $sym }}" {{ old('barcode_symbology', $product->barcode_symbology ?? 'CODE128') == $sym ? 'selected' : '' }}>{{ $sym }}</option>
                    @endforeach
                </select>
                <button type="button" id="scanBarcodeBtn" class="btn btn-outline" title="Scan barcode with camera">
                     Scan
                </button>
            </div>
            <span class="form-hint">Optional. Allows barcode scanning at point of sale.</span>
            <div id="barcode-scanner-container" style="display:none; margin-top:var(--space-3); border:2px solid var(--color-primary); border-radius:var(--radius-md); overflow:hidden; max-width:400px;">
                <video id="barcode-video" style="width:100%; display:block;"></video>
                <div style="padding:var(--space-2); background:var(--color-surface-2); text-align:center;">
                    <button type="button" id="closeScannerBtn" class="btn btn-sm btn-outline">Cancel Scan</button>
                </div>
            </div>
        </div>
        @endif

        {{-- Row 4: Buying Price + Selling Price + profit preview --}}
        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label" for="buying_price">Buying Price (KSh) *</label>
                <input type="number" id="buying_price" name="buying_price"
                       class="form-control"
                       value="{{ old('buying_price', $product->buying_price) }}"
                       step="0.01" min="0" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="selling_price">Selling Price (KSh) *</label>
                <input type="number" id="selling_price" name="selling_price"
                       class="form-control"
                       value="{{ old('selling_price', $product->selling_price) }}"
                       step="0.01" min="0" required>
            </div>
        </div>

        <div id="profitPreview" class="alert alert-success" style="margin-bottom:var(--space-md);">
            &#128200; Profit per unit: <strong id="profitAmount">KSh 0.00</strong>
            &nbsp;|&nbsp; Margin: <strong id="profitMargin">0%</strong>
        </div>

        <div class="form-group">
            <label class="form-label" for="tax_category">Tax Category</label>
            <select id="tax_category" name="tax_category" class="form-control">
                <option value="">Standard (business default)</option>
                <option value="standard" {{ old('tax_category', $product->tax_category) === 'standard' ? 'selected' : '' }}>Standard</option>
                <option value="reduced" {{ old('tax_category', $product->tax_category) === 'reduced' ? 'selected' : '' }}>Reduced</option>
                <option value="zero_rated" {{ old('tax_category', $product->tax_category) === 'zero_rated' ? 'selected' : '' }}>Zero-rated</option>
                <option value="exempt" {{ old('tax_category', $product->tax_category) === 'exempt' ? 'selected' : '' }}>Exempt</option>
            </select>
            <span class="form-hint">Only matters if you've set up rules in Settings &gt; Tax Rules — leave as Standard otherwise.</span>
        </div>

        @if(auth()->user()->currentBusiness()?->etims_enabled)
        <div class="form-group">
            <label class="form-label" for="etims_item_cls_cd">KRA Item Classification Code</label>
            <input type="text" id="etims_item_cls_cd" name="etims_item_cls_cd" class="form-control" maxlength="10"
                   value="{{ old('etims_item_cls_cd', $product->etims_item_cls_cd) }}"
                   placeholder="{{ auth()->user()->currentBusiness()->etims_default_item_cls_cd ?: '10-digit code from KRA' }}">
            <span class="form-hint">Required by KRA eTIMS for every item. Leave blank to use the default set in Settings &gt; eTIMS. Find the right code in KRA's item classification list.</span>
        </div>
        @endif

        {{-- Row 5: Current Stock + Reorder Level | Unit + Status --}}
        <div class="form-grid-2">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="stock_qty">Current Stock *</label>
                    <input type="number" id="stock_qty" name="stock_qty"
                           class="form-control"
                           value="{{ old('stock_qty', $product->stock_qty) }}"
                           min="0" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reorder_level">Reorder Level *</label>
                    <input type="number" id="reorder_level" name="reorder_level"
                           class="form-control"
                           value="{{ old('reorder_level', $product->reorder_level) }}"
                           min="0" required>
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="unit">Sell Unit *</label>
                    <select id="unit" name="unit" class="form-control">
                        @foreach(['piece','kg','litre','box','dozen','packet'] as $u)
                            <option value="{{ $u }}"
                                {{ old('unit', $product->unit) == $u ? 'selected' : '' }}>
                                {{ ucfirst($u) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">Status *</label>
                    <select id="status" name="status" class="form-control">
                        <option value="active"   {{ old('status', $product->status) == 'active'   ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ old('status', $product->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Row 5b: Buy unit --}}
        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label" for="buy_unit">Buy Unit</label>
                <input type="text" id="buy_unit" name="buy_unit" class="form-control"
                       value="{{ old('buy_unit', $product->buy_unit) }}" placeholder="e.g. Carton (optional)">
            </div>
            <div class="form-group">
                <label class="form-label" for="units_per_buy_unit">{{ $product->unit }}s per Buy Unit</label>
                <input type="number" id="units_per_buy_unit" name="units_per_buy_unit" class="form-control"
                       value="{{ old('units_per_buy_unit', $product->units_per_buy_unit ?? 1) }}" min="1">
                <span class="form-hint">e.g. 24 pieces in one Carton</span>
            </div>
        </div>

        {{-- Row 6: Product Image + Gallery --}}
        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label" for="image">Product Image</label>
                @if($product->imageUrl())
                    {{-- Was a bare <img style="max-height:80px;max-width:120px">
                    with no object-fit — a non-square photo (the vast majority
                    of real product photos) just scaled down into a thin,
                    squished, unaligned sliver. .product-image-preview gives it
                    the same fixed-square, centered, object-fit:cover crop the
                    online shop already uses (see shop.css). --}}
                    <div class="product-image-preview" style="margin-bottom:8px;">
                        <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}">
                    </div>
                @endif
                <input type="file" id="image" name="image" class="form-control" accept="image/*">
                <span class="form-hint">Max 1MB. {{ $product->imageUrl() ? 'Uploading a new one replaces the current image.' : 'Shown on the online shop and product lists.' }}</span>
                @error('image')<span class="invalid-feedback">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="gallery">Add Gallery Images</label>
                @if($product->images->isNotEmpty())
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
                    @foreach($product->images as $img)
                    <div class="gallery-thumb-wrap" style="position:relative;">
                        <img src="{{ asset('storage/' . $img->path) }}">
                        <form method="POST" action="{{ route('inventory.images.destroy', [$product, $img]) }}" style="position:absolute; top:-6px; right:-6px;" onsubmit="return confirm('Remove this image?')">
                            @csrf @method('DELETE')
                            <button type="submit" style="width:18px; height:18px; border-radius:50%; background:var(--color-danger); color:#fff; border:none; font-size:11px; line-height:1; cursor:pointer;">&times;</button>
                        </form>
                    </div>
                    @endforeach
                </div>
                @endif
                <input type="file" id="gallery" name="gallery[]" class="form-control" accept="image/*" multiple>
                <span class="form-hint">Adds to the existing gallery — doesn't replace it</span>
            </div>
        </div>

        {{-- Row 7: Visibility --}}
        <div class="form-group">
            <label class="form-label">Visibility</label>
            <div style="display:flex; gap:var(--space-5); flex-wrap:wrap;">
                <label style="display:flex; align-items:center; gap:6px; font-weight:500; cursor:pointer;">
                    <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $product->is_featured) ? 'checked' : '' }}>
                    Featured (shop homepage)
                </label>
                <label style="display:flex; align-items:center; gap:6px; font-weight:500; cursor:pointer;">
                    <input type="checkbox" name="hide_in_pos" value="1" {{ old('hide_in_pos', $product->hide_in_pos) ? 'checked' : '' }}>
                    Hide in POS
                </label>
                <label style="display:flex; align-items:center; gap:6px; font-weight:500; cursor:pointer;">
                    <input type="checkbox" name="hide_in_shop" value="1" {{ old('hide_in_shop', $product->hide_in_shop) ? 'checked' : '' }}>
                    Hide in Online Shop
                </label>
            </div>
        </div>

        {{-- Row 8: Suppliers --}}
        @if($suppliers->isNotEmpty())
        <div class="form-group">
            <label class="form-label">Suppliers</label>
            <div id="supplierRows">
                @foreach($product->suppliers as $ps)
                <div class="form-grid-3" style="align-items:flex-end; margin-bottom:var(--space-3);">
                    <div class="form-group">
                        <label class="form-label">Supplier</label>
                        <select name="supplier_ids[]" class="form-control">
                            <option value="">— Select Supplier —</option>
                            @foreach($suppliers as $s)
                                <option value="{{ $s->id }}" {{ $s->id == $ps->id ? 'selected' : '' }}>{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Part No.</label>
                        <input type="text" name="supplier_part_no[]" class="form-control" value="{{ $ps->pivot->supplier_part_no }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Supplier Price (KSh)</label>
                        <input type="number" name="supplier_price[]" class="form-control" step="0.01" min="0" value="{{ $ps->pivot->supplier_price }}">
                    </div>
                </div>
                @endforeach
            </div>
            <button type="button" id="addSupplierRow" class="btn btn-sm btn-outline" style="margin-top:6px;">+ Add Supplier</button>
            <span class="form-hint">Track which supplier(s) carry this product and at what cost/part number.</span>
        </div>
        <template id="supplierRowTemplate">
            <div class="form-grid-3" style="align-items:flex-end; margin-bottom:var(--space-3);">
                <div class="form-group">
                    <label class="form-label">Supplier</label>
                    <select name="supplier_ids[]" class="form-control">
                        <option value="">— Select Supplier —</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Part No.</label>
                    <input type="text" name="supplier_part_no[]" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Supplier Price (KSh)</label>
                    <input type="number" name="supplier_price[]" class="form-control" step="0.01" min="0">
                </div>
            </div>
        </template>
        @endif

        {{-- Row 9: Description --}}
        <div class="form-group">
            <label class="form-label" for="description">Description</label>
            <textarea id="description" name="description"
                      class="form-control" rows="3"
                      placeholder="Optional product description">{{ old('description', $product->description) }}</textarea>
        </div>

        {{-- Tracking Options --}}
        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label" style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="track_batches" value="1"
                        {{ old('track_batches', $product->track_batches) ? 'checked' : '' }}>
                    Track Expiry Batches
                </label>
                <span class="form-hint">Enable batch tracking with expiry dates (pharmacies, groceries)</span>
            </div>
            <div class="form-group">
                <label class="form-label">Expiry Alert Days</label>
                <input type="number" name="expiry_alert_days" class="form-control"
                    value="{{ old('expiry_alert_days', $product->expiry_alert_days ?? 30) }}"
                    min="1" max="365">
            </div>
            <div class="form-group">
                <label class="form-label" style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="track_serials" value="1"
                        {{ old('track_serials', $product->track_serials) ? 'checked' : '' }}>
                    Track Serial Numbers
                </label>
                <span class="form-hint">Enable individual unit tracking by serial number</span>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">&#128190; Update Product</button>
            <a href="{{ route('inventory.index') }}" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

{{-- Variants Section --}}
<div class="form-card" style="max-width:780px; margin-top:var(--space-lg);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-md);">
        <div>
            <h3 style="margin:0; font-size:var(--text-base);">Product Variants</h3>
            <p style="margin:4px 0 0; color:var(--color-text-muted); font-size:var(--text-sm);">
                Manage sizes, colours, units etc.
            </p>
        </div>
        <a href="{{ route('inventory.variants.index', $product) }}" class="btn btn-secondary">
            Manage Variants ({{ $product->variants()->count() }})
        </a>
    </div>
    @if($product->has_variants)
        <div class="alert alert-warning" style="margin:0;">
            This product has variants enabled. Stock is tracked per-variant.
        </div>
    @endif
</div>

{{-- Price Tier Section --}}
@if($priceTiers->isNotEmpty() && auth()->user()->hasAnyRole('owner', 'manager'))
<div class="form-card" style="max-width:780px; margin-top:var(--space-lg);">
    <h3 style="margin:0; font-size:var(--text-base);">Tier Prices</h3>
    <p style="margin:4px 0 var(--space-md); color:var(--color-text-muted); font-size:var(--text-sm);">
        Special prices for customers assigned to a price tier (Customers &rarr; Edit &rarr; Price Tier).
        Leave a tier blank to use the normal selling price (KSh {{ number_format($product->selling_price, 2) }}).
        Tier prices apply on the online shop for logged-in customers.
    </p>
    <form method="POST" action="{{ route('inventory.prices.update', $product) }}">
        @csrf
        @method('PATCH')
        @foreach($priceTiers as $i => $tier)
            <div class="form-group" style="display:flex; align-items:center; gap:var(--space-md); margin-bottom:var(--space-sm);">
                <input type="hidden" name="prices[{{ $i }}][tier_id]" value="{{ $tier->id }}">
                <label class="form-label" style="flex:1; margin:0;">{{ $tier->name }}@if($tier->is_default) <span style="color:var(--color-text-muted); font-size:var(--text-sm);">(default)</span>@endif</label>
                <input type="number" step="0.01" min="0" name="prices[{{ $i }}][price]" class="form-control"
                       style="max-width:180px;" placeholder="{{ number_format($product->selling_price, 2, '.', '') }}"
                       value="{{ isset($tierPrices[$tier->id]) ? number_format($tierPrices[$tier->id], 2, '.', '') : '' }}">
            </div>
        @endforeach
        <button type="submit" class="btn btn-primary">Save Tier Prices</button>
    </form>
</div>
@endif

{{-- Batch Tracking Section --}}
@if($product->track_batches)
<div class="form-card" style="max-width:780px; margin-top:var(--space-md);">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h3 style="margin:0; font-size:var(--text-base);">Expiry Batches</h3>
            <p style="margin:4px 0 0; color:var(--color-text-muted); font-size:var(--text-sm);">Track batches with expiry dates</p>
        </div>
        <a href="{{ route('inventory.batches.index', $product) }}" class="btn btn-secondary">Manage Batches</a>
    </div>
</div>
@endif

{{-- Serial Tracking Section --}}
@if($product->track_serials)
<div class="form-card" style="max-width:780px; margin-top:var(--space-md);">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h3 style="margin:0; font-size:var(--text-base);">Serial Numbers</h3>
            <p style="margin:4px 0 0; color:var(--color-text-muted); font-size:var(--text-sm);">Track individual units by serial number</p>
        </div>
        <a href="{{ route('inventory.serials.index', $product) }}" class="btn btn-secondary">Manage Serials</a>
    </div>
</div>
@endif

@endsection

@push('scripts')
    <script src="{{ asset('js/inventory.js') }}?v={{ @filemtime(public_path('js/inventory.js')) ?: '1' }}"></script>
    <script>
    // ── Sub-category: populate from the chosen category's data-children ──
    (function () {
        var categorySelect = document.getElementById('category_id');
        var subSelect = document.getElementById('sub_category_id');
        if (!categorySelect || !subSelect) return;
        var oldSubCategoryId = '{{ old('sub_category_id', $selectedSubCategoryId) }}';

        function refresh() {
            var opt = categorySelect.options[categorySelect.selectedIndex];
            var children = {};
            try { children = JSON.parse((opt && opt.getAttribute('data-children')) || '{}'); } catch (e) {}
            subSelect.innerHTML = '<option value="">— None —</option>';
            Object.keys(children).forEach(function (id) {
                var o = document.createElement('option');
                o.value = id;
                o.textContent = children[id];
                if (String(id) === oldSubCategoryId) o.selected = true;
                subSelect.appendChild(o);
            });
        }
        categorySelect.addEventListener('change', refresh);
        refresh();
    })();

    // ── Buy-unit label follows the chosen Sell Unit ──
    (function () {
        var unitSelect = document.getElementById('unit');
        var label = document.querySelector('label[for="units_per_buy_unit"]');
        if (!unitSelect || !label) return;
        unitSelect.addEventListener('change', function () {
            label.textContent = unitSelect.options[unitSelect.selectedIndex].text + 's per Buy Unit';
        });
    })();

    // ── Supplier rows: existing ones are already rendered server-side —
    // only add a first blank row here if the product has none yet. ──
    (function () {
        var container = document.getElementById('supplierRows');
        var template  = document.getElementById('supplierRowTemplate');
        var addBtn    = document.getElementById('addSupplierRow');
        if (!container || !template) return;

        function addRow() {
            container.appendChild(template.content.cloneNode(true));
        }
        if (container.children.length === 0) addRow();
        if (addBtn) addBtn.addEventListener('click', addRow);
    })();
    </script>
    @if(auth()->user()->currentBusiness()?->hasFeature('barcode'))
    <script>
    (function() {
        var btn = document.getElementById('scanBarcodeBtn');
        var closeBtn = document.getElementById('closeScannerBtn');
        var container = document.getElementById('barcode-scanner-container');
        var video = document.getElementById('barcode-video');
        var barcodeInput = document.getElementById('barcode');
        var stream = null;
        if (!btn) return;
        btn.addEventListener('click', function() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { alert('Camera not supported. Please type the barcode manually.'); return; }
            container.style.display = 'block'; btn.disabled = true;
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then(function(s) { stream = s; video.srcObject = s; video.play(); scanLoop(); })
                .catch(function() { container.style.display = 'none'; btn.disabled = false; alert('Could not access camera. Please type the barcode manually.'); });
        });
        closeBtn.addEventListener('click', stopScanner);
        function stopScanner() { if (stream) { stream.getTracks().forEach(function(t){ t.stop(); }); stream = null; } container.style.display = 'none'; btn.disabled = false; }
        function scanLoop() {
            if (!stream) return;
            if (!('BarcodeDetector' in window)) { stopScanner(); alert('Barcode detection not supported. Please type manually.'); return; }
            var detector = new BarcodeDetector({ formats: ['ean_13','ean_8','code_128','code_39','upc_a','upc_e','qr_code'] });
            var canvas = document.createElement('canvas');
            function detect() {
                if (!stream) return;
                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    canvas.width = video.videoWidth; canvas.height = video.videoHeight;
                    canvas.getContext('2d').drawImage(video, 0, 0);
                    detector.detect(canvas).then(function(b) { if (b.length > 0) { barcodeInput.value = b[0].rawValue; stopScanner(); return; } requestAnimationFrame(detect); }).catch(function() { requestAnimationFrame(detect); });
                } else { requestAnimationFrame(detect); }
            }
            detect();
        }
    })();
    </script>
    @endif
@endpush
