@extends('layouts.app')
@section('title', 'Add Product')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/inventory.css') }}?v={{ @filemtime(public_path('css/inventory.css')) ?: '1' }}">
@endpush

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">&#43; Add Product</h1>
        <p class="page-subtitle">Add a new product to your inventory</p>
    </div>
    <a href="{{ route('inventory.index') }}" class="btn btn-outline">&#8592; Back to Inventory</a>
</div>

<div class="form-card" style="max-width:780px;">
    <form method="POST" action="{{ route('inventory.store') }}" enctype="multipart/form-data">
        @csrf

        {{-- Row 1: Product Name --}}
        <div class="form-group">
            <label class="form-label" for="name">Product Name *</label>
            <input type="text" id="name" name="name"
                   class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                   value="{{ old('name') }}"
                   placeholder="e.g. Maize Flour 2kg"
                   required autofocus
                   autocomplete="off">
            @error('name')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror

            {{-- Duplicate product warning --}}
            <div id="duplicateWarning" class="alert alert-warning" style="display:none; margin-top:10px;">
                <div style="font-weight:700; margin-bottom:6px;">This product may already exist</div>
                <div id="duplicateMatches"></div>
                <div style="margin-top:8px; font-size:13px;">
                    To add more stock to an existing product, use
                    <a href="{{ route('inventory.adjustments.create') }}" style="color:var(--color-warning); font-weight:600;">Stock Adjustment</a>
                    or
                    <a href="{{ route('receives.create') }}" style="color:var(--color-warning); font-weight:600;">Receive Stock</a>
                    instead of adding a new product.
                </div>
                <button type="button" onclick="document.getElementById('duplicateWarning').style.display='none'"
                    style="margin-top:8px; font-size:12px; background:none; border:none; color:inherit; cursor:pointer; text-decoration:underline;">
                    No, this is a different product — continue
                </button>
            </div>
        </div>

        {{-- Row 2: SKU + Category --}}
        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label" for="sku">SKU</label>
                <input type="text" id="sku" name="sku"
                       class="form-control {{ $errors->has('sku') ? 'is-invalid' : '' }}"
                       value="{{ old('sku') }}"
                       placeholder="Auto-generated if blank">
                <span class="form-hint">Leave blank to auto-generate</span>
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
                            {{ old('category_id') == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Row 2b: Sub-category (populated from the chosen category's
             children — see the categoryData script below) + Brand --}}
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
                <input type="text" id="brand" name="brand" class="form-control" list="brandOptions"
                       value="{{ old('brand') }}" placeholder="e.g. Coca-Cola">
                <datalist id="brandOptions">
                    @foreach($existingProducts->pluck('brand')->filter()->unique() as $b)
                        <option value="{{ $b }}"></option>
                    @endforeach
                </datalist>
            </div>
        </div>

        {{-- Row 3: Barcode (feature-gated) --}}
        @if(auth()->user()->currentBusiness()?->hasFeature('barcode'))
        <div class="form-group">
            <label class="form-label" for="barcode">Barcode</label>
            <div style="display:flex; gap:var(--space-2);">
                <input type="text" id="barcode" name="barcode" class="form-control"
                       value="{{ old('barcode') }}"
                       placeholder="Scan or type barcode (EAN, UPC, QR...)">
                <select id="barcode_symbology" name="barcode_symbology" class="form-control" style="max-width:140px;">
                    <option value="CODE128" {{ old('barcode_symbology','CODE128') == 'CODE128' ? 'selected' : '' }}>CODE128</option>
                    <option value="EAN13"   {{ old('barcode_symbology') == 'EAN13'   ? 'selected' : '' }}>EAN13</option>
                    <option value="EAN8"    {{ old('barcode_symbology') == 'EAN8'    ? 'selected' : '' }}>EAN8</option>
                    <option value="UPC"     {{ old('barcode_symbology') == 'UPC'     ? 'selected' : '' }}>UPC</option>
                    <option value="CODE39"  {{ old('barcode_symbology') == 'CODE39'  ? 'selected' : '' }}>CODE39</option>
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
                       class="form-control {{ $errors->has('buying_price') ? 'is-invalid' : '' }}"
                       value="{{ old('buying_price', 0) }}"
                       step="0.01" min="0" required>
                @error('buying_price')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="selling_price">Selling Price (KSh) *</label>
                <input type="number" id="selling_price" name="selling_price"
                       class="form-control {{ $errors->has('selling_price') ? 'is-invalid' : '' }}"
                       value="{{ old('selling_price', 0) }}"
                       step="0.01" min="0" required>
                @error('selling_price')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div id="profitPreview" class="alert alert-success" style="margin-bottom:var(--space-md); display:none;">
            &#128200; Profit per unit: <strong id="profitAmount">KSh 0.00</strong>
            &nbsp;|&nbsp; Margin: <strong id="profitMargin">0%</strong>
        </div>

        <div class="form-group">
            <label class="form-label" for="tax_category">Tax Category</label>
            <select id="tax_category" name="tax_category" class="form-control">
                <option value="">Standard (business default)</option>
                <option value="standard" {{ old('tax_category') === 'standard' ? 'selected' : '' }}>Standard</option>
                <option value="reduced" {{ old('tax_category') === 'reduced' ? 'selected' : '' }}>Reduced</option>
                <option value="zero_rated" {{ old('tax_category') === 'zero_rated' ? 'selected' : '' }}>Zero-rated</option>
                <option value="exempt" {{ old('tax_category') === 'exempt' ? 'selected' : '' }}>Exempt</option>
            </select>
            <span class="form-hint">Only matters if you've set up rules in Settings &gt; Tax Rules — leave as Standard otherwise.</span>
        </div>

        @if(auth()->user()->currentBusiness()?->etims_enabled)
        <div class="form-group">
            <label class="form-label" for="etims_item_cls_cd">KRA Item Classification Code</label>
            <input type="text" id="etims_item_cls_cd" name="etims_item_cls_cd" class="form-control" maxlength="10"
                   value="{{ old('etims_item_cls_cd') }}"
                   placeholder="{{ auth()->user()->currentBusiness()->etims_default_item_cls_cd ?: '10-digit code from KRA' }}">
            <span class="form-hint">Required by KRA eTIMS for every item. Leave blank to use the default set in Settings &gt; eTIMS. Find the right code in KRA's item classification list.</span>
        </div>
        @endif

        {{-- Row 5: Opening Stock + Reorder Level | Unit + Status --}}
        <div class="form-grid-2">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="stock_qty">Opening Stock *</label>
                    <input type="number" id="stock_qty" name="stock_qty"
                           class="form-control {{ $errors->has('stock_qty') ? 'is-invalid' : '' }}"
                           value="{{ old('stock_qty', 0) }}"
                           min="0" required>
                    @error('stock_qty')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="reorder_level">Reorder Level *</label>
                    <input type="number" id="reorder_level" name="reorder_level"
                           class="form-control {{ $errors->has('reorder_level') ? 'is-invalid' : '' }}"
                           value="{{ old('reorder_level', 5) }}"
                           min="0" required>
                    <span class="form-hint">Alert when stock drops below this</span>
                    @error('reorder_level')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="unit">Sell Unit *</label>
                    <select id="unit" name="unit"
                            class="form-control {{ $errors->has('unit') ? 'is-invalid' : '' }}">
                        <option value="piece"  {{ old('unit','piece') == 'piece'  ? 'selected' : '' }}>Piece</option>
                        <option value="kg"     {{ old('unit') == 'kg'     ? 'selected' : '' }}>Kilogram (kg)</option>
                        <option value="litre"  {{ old('unit') == 'litre'  ? 'selected' : '' }}>Litre</option>
                        <option value="box"    {{ old('unit') == 'box'    ? 'selected' : '' }}>Box</option>
                        <option value="dozen"  {{ old('unit') == 'dozen'  ? 'selected' : '' }}>Dozen</option>
                        <option value="packet" {{ old('unit') == 'packet' ? 'selected' : '' }}>Packet</option>
                    </select>
                    <span class="form-hint">The unit stock is tracked and sold in</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">Status *</label>
                    <select id="status" name="status" class="form-control">
                        <option value="active"   {{ old('status','active') == 'active'   ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Row 5b: Buy unit — optional, only matters when you purchase in a
             different unit than you sell (e.g. buy a Carton of 24, sell by
             the Piece). Stock stays tracked in the Sell Unit above always;
             this just lets Receive Stock convert for you instead of you
             doing the multiplication by hand. --}}
        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label" for="buy_unit">Buy Unit</label>
                <input type="text" id="buy_unit" name="buy_unit" class="form-control"
                       value="{{ old('buy_unit') }}" placeholder="e.g. Carton (optional)">
            </div>
            <div class="form-group">
                <label class="form-label" for="units_per_buy_unit">{{ old('unit','pieces') }} per Buy Unit</label>
                <input type="number" id="units_per_buy_unit" name="units_per_buy_unit" class="form-control"
                       value="{{ old('units_per_buy_unit', 1) }}" min="1">
                <span class="form-hint">e.g. 24 pieces in one Carton</span>
            </div>
        </div>

        {{-- Row 6: Product Image + Gallery --}}
        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label" for="image">Product Image</label>
                <input type="file" id="image" name="image" class="form-control" accept="image/*">
                <span class="form-hint">Max 1MB. Shown on the online shop and product lists.</span>
                @error('image')<span class="invalid-feedback">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="gallery">Gallery Images</label>
                <input type="file" id="gallery" name="gallery[]" class="form-control" accept="image/*" multiple>
                <span class="form-hint">Optional — extra photos shown on the product's shop page</span>
            </div>
        </div>

        {{-- Row 7: Visibility --}}
        <div class="form-group">
            <label class="form-label">Visibility</label>
            <div style="display:flex; gap:var(--space-5); flex-wrap:wrap;">
                <label style="display:flex; align-items:center; gap:6px; font-weight:500; cursor:pointer;">
                    <input type="checkbox" name="is_featured" value="1" {{ old('is_featured') ? 'checked' : '' }}>
                    Featured (shop homepage)
                </label>
                <label style="display:flex; align-items:center; gap:6px; font-weight:500; cursor:pointer;">
                    <input type="checkbox" name="hide_in_pos" value="1" {{ old('hide_in_pos') ? 'checked' : '' }}>
                    Hide in POS
                </label>
                <label style="display:flex; align-items:center; gap:6px; font-weight:500; cursor:pointer;">
                    <input type="checkbox" name="hide_in_shop" value="1" {{ old('hide_in_shop') ? 'checked' : '' }}>
                    Hide in Online Shop
                </label>
            </div>
        </div>

        {{-- Row 8: Suppliers --}}
        @if($suppliers->isNotEmpty())
        <div class="form-group">
            <label class="form-label">Suppliers</label>
            <div id="supplierRows"></div>
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
                      placeholder="Optional product description">{{ old('description') }}</textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">&#128190; Save Product</button>
            <a href="{{ route('inventory.index') }}" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

@endsection

@push('scripts')
    <script src="{{ asset('js/inventory.js') }}?v={{ @filemtime(public_path('js/inventory.js')) ?: '1' }}"></script>
    <script>
    // ── Sub-category: populate from the chosen category's data-children ──
    (function () {
        var categorySelect = document.getElementById('category_id');
        var subSelect = document.getElementById('sub_category_id');
        if (!categorySelect || !subSelect) return;
        var oldSubCategoryId = '{{ old('sub_category_id') }}';

        function refresh() {
            var opt = categorySelect.options[categorySelect.selectedIndex];
            var children = {};
            try { children = JSON.parse(opt.getAttribute('data-children') || '{}'); } catch (e) {}
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

    // ── Supplier rows: add one by default, "+ Add Supplier" clones more ──
    (function () {
        var container = document.getElementById('supplierRows');
        var template  = document.getElementById('supplierRowTemplate');
        var addBtn    = document.getElementById('addSupplierRow');
        if (!container || !template) return;

        function addRow() {
            container.appendChild(template.content.cloneNode(true));
        }
        addRow();
        if (addBtn) addBtn.addEventListener('click', addRow);
    })();

    (function () {
        @php
            // @json() can't reliably parse a directly-inlined multi-key
            // array literal inside a ->map(fn()=>[...]) closure (its
            // argument-extraction regex stops at the first ')' it sees,
            // silently truncating the array and breaking the whole page
            // with a PHP ParseError) — build the plain array first, then
            // @json() the already-resolved variable instead.
            $existingProductsForJs = $existingProducts->map(fn($p) => [
                'id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'stock' => $p->stock_qty,
            ]);
        @endphp
        var EXISTING = @json($existingProductsForJs);
        var nameInput = document.getElementById('name');
        var warning   = document.getElementById('duplicateWarning');
        var matchBox  = document.getElementById('duplicateMatches');
        var timer     = null;

        function similarity(a, b) {
            a = a.toLowerCase().trim();
            b = b.toLowerCase().trim();
            if (a === b) return 1;
            if (b.includes(a) || a.includes(b)) return 0.85;
            // Check word overlap
            var wa = a.split(/\s+/);
            var wb = b.split(/\s+/);
            var common = wa.filter(function(w) { return w.length > 2 && wb.includes(w); }).length;
            return common / Math.max(wa.length, wb.length);
        }

        nameInput.addEventListener('input', function () {
            clearTimeout(timer);
            var val = this.value.trim();
            if (val.length < 3) { warning.style.display = 'none'; return; }

            timer = setTimeout(function () {
                var matches = EXISTING.filter(function (p) {
                    return similarity(val, p.name) >= 0.6;
                }).slice(0, 4);

                if (matches.length === 0) {
                    warning.style.display = 'none';
                    return;
                }

                matchBox.innerHTML = matches.map(function (p) {
                    return '<div style="background:var(--color-surface); border:1px solid var(--color-warning); border-radius:6px; padding:8px 10px; margin-bottom:6px; font-size:13px;">'
                        + '<strong>' + p.name + '</strong>'
                        + ' <span class="text-muted">(' + p.sku + ')</span>'
                        + ' — <span class="text-success">Stock: ' + p.stock + '</span>'
                        + ' &nbsp;<a href="/inventory/' + p.id + '/edit" style="font-size:12px; color:var(--color-primary);">Edit product →</a>'
                        + '</div>';
                }).join('');

                warning.style.display = 'block';
            }, 400);
        });
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
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Camera access is not supported in this browser. Please type the barcode manually.');
                return;
            }
            container.style.display = 'block';
            btn.disabled = true;
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then(function(s) {
                    stream = s;
                    video.srcObject = s;
                    video.play();
                    scanLoop();
                })
                .catch(function() {
                    container.style.display = 'none';
                    btn.disabled = false;
                    alert('Could not access camera. Please type the barcode manually.');
                });
        });

        closeBtn.addEventListener('click', stopScanner);

        function stopScanner() {
            if (stream) { stream.getTracks().forEach(function(t){ t.stop(); }); stream = null; }
            container.style.display = 'none';
            btn.disabled = false;
        }

        function scanLoop() {
            if (!stream) return;
            if (!('BarcodeDetector' in window)) {
                stopScanner();
                alert('Barcode detection is not supported in this browser. Please type the barcode manually.');
                return;
            }
            var detector = new BarcodeDetector({ formats: ['ean_13','ean_8','code_128','code_39','upc_a','upc_e','qr_code'] });
            var canvas = document.createElement('canvas');
            function detect() {
                if (!stream) return;
                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    canvas.getContext('2d').drawImage(video, 0, 0);
                    detector.detect(canvas).then(function(barcodes) {
                        if (barcodes.length > 0) {
                            barcodeInput.value = barcodes[0].rawValue;
                            stopScanner();
                            return;
                        }
                        requestAnimationFrame(detect);
                    }).catch(function() { requestAnimationFrame(detect); });
                } else {
                    requestAnimationFrame(detect);
                }
            }
            detect();
        }
    })();
    </script>
    @endif
@endpush
