<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Http\Requests\CategoryRequest;
use App\Http\Requests\ProductImportRequest;
use App\Jobs\ImportProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class InventoryController extends Controller {

    // â”€â”€ Helper: get current business id â”€â”€â”€â”€â”€â”€â”€
    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    //  PRODUCTS
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

    // List all products
    public function index(Request $request) {
        $businessId = $this->businessId();
        $query      = Product::with('category')
                        ->forBusiness($businessId);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku',  'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where(
                'category_id', $request->category_id
            );
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter low stock only
        if ($request->boolean('low_stock')) {
            $query->lowStock();
        }

        $products   = $query->orderBy('name')
                        ->paginate(15)
                        ->withQueryString();

        $categories = Category::where(
                        'business_id', $businessId
                      )->orderBy('name')->get();

        // Summary stats
        $stats = [
            'total'     => Product::forBusiness($businessId)
                            ->count(),
            'active'    => Product::forBusiness($businessId)
                            ->active()->count(),
            'low_stock' => Product::forBusiness($businessId)
                            ->lowStock()->count(),
            'value'     => Product::forBusiness($businessId)
                            ->active()
                            ->selectRaw(
                                'SUM(stock_qty * buying_price) 
                                as total_value'
                            )
                            ->value('total_value') ?? 0,
        ];

        return view('inventory.index', compact(
            'products', 'categories', 'stats'
        ));
    }

    // Show create product form
    public function create() {
        $businessId = $this->businessId();
        // Top-level only — the form renders sub-categories per top-level
        // pick from this same collection via each category's ->children,
        // rather than a second query per request.
        $categories = Category::where('business_id', $businessId)
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('name')->get();

        $suppliers = Supplier::where('business_id', $businessId)
            ->orderBy('name')->get();

        // Pass existing products so the form can warn about duplicates before saving
        $existingProducts = \App\Models\Product::where('business_id', $businessId)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'stock_qty', 'brand']);

        return view('inventory.create', compact('categories', 'suppliers', 'existingProducts'));
    }

    // Store new product
    public function store(ProductRequest $request) {
        $business = Auth::user()->currentBusiness();
        $limit    = $business->planLimit('products');
        $current  = Product::forBusiness($business->id)->count();

        if ($current >= $limit) {
            $org = $business->organization;
            // planName() returns "Trial" while on trial (by design, for
            // status banners) — that reads as nonsense here ("...limit on
            // the Trial plan"), so use the real underlying plan name instead.
            $currentPlanName = $org ? $org->planConfig()['name'] : $business->planConfig()['name'];
            $nextPlan        = $org?->upgradePlan();
            $upgradeName     = $nextPlan ? ucfirst($nextPlan) : 'a higher';

            return redirect()->route('inventory.index')
                ->with('error', "You have reached the {$limit}-product limit on the {$currentPlanName} plan. Upgrade to {$upgradeName} for more products.");
        }

        $data = $request->validated();
        $data['business_id'] = $this->businessId();

        // A chosen sub-category IS the category — it's just a Category row
        // whose own parent_id happens to be set, so no second column is
        // needed on products for this. Falls back to the top-level pick
        // when no sub-category was chosen.
        if (!empty($data['sub_category_id'])) {
            $data['category_id'] = $data['sub_category_id'];
        }
        unset($data['sub_category_id'], $data['gallery'], $data['supplier_ids'], $data['supplier_part_no'], $data['supplier_price']);

        // Auto-generate SKU if blank
        if (empty($data['sku'])) {
            $data['sku'] = Product::generateSku(
                $data['name'],
                $data['business_id']
            );
        }

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($data);

        $this->storeGalleryImages($product, $request);
        $this->syncProductSuppliers($product, $request);

        return redirect()
            ->route('inventory.index')
            ->with('success',
                'Product added successfully.');
    }

    // ── New product-detail helpers ──────────────────────────────────────

    private function storeGalleryImages(Product $product, Request $request): void {
        if (!$request->hasFile('gallery')) {
            return;
        }
        $nextOrder = (int) $product->images()->max('sort_order');
        foreach ($request->file('gallery') as $file) {
            if (!$file || !$file->isValid()) continue;
            $nextOrder++;
            ProductImage::create([
                'product_id' => $product->id,
                'path'       => $file->store('products/gallery', 'public'),
                'sort_order' => $nextOrder,
            ]);
        }
    }

    // Replaces the product's supplier list with whatever was submitted —
    // simplest correct behaviour for a form that re-renders the full
    // current list each time, rather than diffing add/remove ourselves.
    private function syncProductSuppliers(Product $product, Request $request): void {
        $businessId = $this->businessId();
        $ids       = $request->input('supplier_ids', []);
        $partNos   = $request->input('supplier_part_no', []);
        $prices    = $request->input('supplier_price', []);

        $sync = [];
        foreach ($ids as $i => $supplierId) {
            if (empty($supplierId)) continue;
            // Re-validated here, not just in the FormRequest — a supplier_id
            // could otherwise reference another business's supplier row.
            $belongsToBusiness = Supplier::where('id', $supplierId)
                ->where('business_id', $businessId)->exists();
            if (!$belongsToBusiness) continue;

            $sync[$supplierId] = [
                'supplier_part_no' => $partNos[$i] ?? null,
                'supplier_price'   => (isset($prices[$i]) && $prices[$i] !== '') ? $prices[$i] : null,
            ];
        }

        $product->suppliers()->sync($sync);
    }

    // Show single product
    public function show(Product $product) {
        $this->authorizeProduct($product);

        $product->load('category');

        return view('inventory.show', compact('product'));
    }

    // Show edit form
    public function edit(Product $product) {
        $this->authorizeProduct($product);
        $businessId = $this->businessId();

        $categories = Category::where('business_id', $businessId)
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('name')->get();

        $suppliers = Supplier::where('business_id', $businessId)
            ->orderBy('name')->get();

        $product->load(['images', 'suppliers', 'category']);

        // The product's category might itself be a sub-category — in that
        // case the top-level select needs its parent preselected, and the
        // sub-category select needs the actual category_id preselected.
        // Otherwise the current category simply IS the top-level pick.
        $selectedTopCategoryId = $product->category?->isSubCategory()
            ? $product->category->parent_id
            : $product->category_id;
        $selectedSubCategoryId = $product->category?->isSubCategory()
            ? $product->category_id
            : null;

        $priceTiers = \App\Models\PriceTier::forBusiness($businessId)->orderBy('name')->get();
        $tierPrices = $product->priceTiers()->pluck('price', 'price_tier_id');

        return view('inventory.edit', compact(
            'product', 'categories', 'suppliers',
            'selectedTopCategoryId', 'selectedSubCategoryId',
            'priceTiers', 'tierPrices'
        ));
    }

    // Update product
    public function update(
        ProductRequest $request,
        Product $product
    ) {
        $this->authorizeProduct($product);

        $data = $request->validated();

        // Unticked checkboxes aren't submitted at all, so validated() simply
        // omits them — previously a product that was ever set to Featured /
        // Hide in POS / Hide in Shop could never be switched back, and the
        // batch / serial tracking boxes were dropped entirely (no rule for
        // them), leaving those features unreachable. Set them explicitly.
        foreach (['is_featured', 'hide_in_pos', 'hide_in_shop', 'track_batches', 'track_serials'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }
        if (empty($data['expiry_alert_days'])) {
            unset($data['expiry_alert_days']);
        }

        if (!empty($data['sub_category_id'])) {
            $data['category_id'] = $data['sub_category_id'];
        }
        unset($data['sub_category_id'], $data['gallery'], $data['supplier_ids'], $data['supplier_part_no'], $data['supplier_price'], $data['image']);

        if (empty($data['sku'])) {
            $data['sku'] = Product::generateSku(
                $data['name'],
                $this->businessId()
            );
        }

        if ($request->hasFile('image')) {
            // Old file is left in storage rather than deleted — same
            // trade-off as the business logo elsewhere in this app,
            // avoids a broken image if something else still references
            // the old path (e.g. a cached shop listing page).
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        // Price changes are worth an audit trail: they change what customers
        // are charged and what profit reports show.
        $oldPrices = ['selling' => (float) $product->selling_price, 'buying' => (float) $product->buying_price];
        $priceChanged = (isset($data['selling_price']) && (float) $data['selling_price'] !== $oldPrices['selling'])
            || (isset($data['buying_price']) && (float) $data['buying_price'] !== $oldPrices['buying']);

        // KRA holds the item's class code and tax type from when it was
        // registered. If either changes, mark it for re-registration so the
        // next sale updates the item at KRA instead of using stale details.
        if ($product->etims_registered_at
            && (array_key_exists('etims_item_cls_cd', $data) && ($data['etims_item_cls_cd'] ?: null) !== ($product->etims_item_cls_cd ?: null)
                || (array_key_exists('tax_category', $data) && ($data['tax_category'] ?: null) !== ($product->tax_category ?: null)))) {
            $data['etims_registered_at'] = null;
        }

        $product->update($data);

        if ($priceChanged) {
            \App\Models\AuditLog::record('product.price_changed', $product, [
                'name' => $product->name,
                'selling_from' => $oldPrices['selling'], 'selling_to' => (float) $product->selling_price,
                'buying_from'  => $oldPrices['buying'],  'buying_to'  => (float) $product->buying_price,
            ]);
        }

        $this->storeGalleryImages($product, $request);
        $this->syncProductSuppliers($product, $request);

        return redirect()
            ->route('inventory.index')
            ->with('success',
                'Product updated successfully.');
    }

    // Remove one gallery image (AJAX-friendly, but works as a plain form
    // post too).
    public function destroyImage(Product $product, ProductImage $image) {
        $this->authorizeProduct($product);
        abort_if($image->product_id !== $product->id, 404);

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return back()->with('success', 'Image removed.');
    }

    // Bulk action on selected products
    public function bulkAction(Request $request) {
        $request->validate([
            'bulk_action' => ['required', 'in:activate,deactivate,delete'],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer'],
        ]);

        $businessId = $this->businessId();
        $ids        = $request->product_ids;

        // Scope to this business â€” prevents cross-tenant manipulation
        $query = Product::forBusiness($businessId)->whereIn('id', $ids);

        switch ($request->bulk_action) {
            case 'activate':
                $count = $query->update(['status' => 'active']);
                $msg   = "{$count} product(s) activated.";
                break;

            case 'deactivate':
                $count = $query->update(['status' => 'inactive']);
                $msg   = "{$count} product(s) deactivated.";
                break;

            case 'delete':
                $count = $query->count();
                $query->delete();
                $msg   = "{$count} product(s) deleted.";
                break;
        }

        return redirect()
            ->route('inventory.index')
            ->with('success', $msg ?? 'Done.');
    }

    // Delete product
    public function destroy(Product $product) {
        $this->authorizeProduct($product);

        $product->delete();

        return redirect()
            ->route('inventory.index')
            ->with('success', 
                'Product deleted successfully.');
    }

    // Show CSV import form
    public function importForm() {
        return view('inventory.import');
    }

    // Download blank CSV template
    public function importTemplate() {
        $headers = [
            'name', 'selling_price', 'buying_price',
            'sku', 'category', 'stock_qty',
            'reorder_level', 'unit', 'description', 'status',
        ];

        $example = [
            'Maize Flour 2kg', '150', '95', 'MF-2KG',
            'Grocery', '50', '10', 'pcs',
            'White maize flour 2kg pack', 'active',
        ];

        $csv  = implode(',', $headers) . "\n";
        $csv .= implode(',', $example) . "\n";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="products_template.csv"',
        ]);
    }

    // Handle CSV import upload
    public function import(ProductImportRequest $request) {
        $business = Auth::user()->currentBusiness();
        $limit    = $business->planLimit('products');

        // Ensure import directory exists, then store the uploaded file
        \Illuminate\Support\Facades\Storage::disk('local')->makeDirectory('imports');

        $uploadedFile = $request->file('import_file');
        $extension    = $uploadedFile->getClientOriginalExtension();

        $path = $uploadedFile
            ->storeAs('imports', 'products_' . $business->id . '_' . time() . '.' . $extension, 'local');

        $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);

        // Run synchronously â€” file is small (max 2MB) and gives instant feedback
        $job     = new ImportProducts($fullPath, $business->id, $limit);
        $results = $job->handle();

        $message = "Import complete: {$results['imported']} imported, {$results['skipped']} skipped.";

        return redirect()
            ->route('inventory.index')
            ->with('import_results', $results)
            ->with('success', $message);
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    //  EXPORT
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

    public function export(Request $request) {
        $businessId = $this->businessId();
        $query = Product::with('category')->forBusiness($businessId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->boolean('low_stock')) {
            $query->lowStock();
        }

        $products = $query->orderBy('name')->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="inventory_' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($products) {
            $handle = fopen('php://output', 'w');
            \App\Support\Csv::put($handle, ['SKU', 'Name', 'Category', 'Selling Price', 'Buying Price', 'Stock Qty', 'Reorder Level', 'Unit', 'Status', 'Barcode', 'Description']);
            foreach ($products as $p) {
                \App\Support\Csv::put($handle, [
                    $p->sku,
                    $p->name,
                    $p->category->name ?? '',
                    $p->selling_price,
                    $p->buying_price,
                    $p->stock_qty,
                    $p->reorder_level,
                    $p->unit,
                    $p->status,
                    $p->barcode ?? '',
                    $p->description ?? '',
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    //  CATEGORIES
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

    // List categories
    public function categories() {
        $businessId = $this->businessId();

        $categories = Category::withCount('products')
                        ->where('business_id', $businessId)
                        ->with('parent')
                        ->orderBy('name')
                        ->get();

        // Parent-picker options — a sub-category can't itself be chosen as
        // a parent (see CategoryRequest), so only top-level ones are listed.
        $topCategories = $categories->whereNull('parent_id')->values();

        return view('inventory.categories', compact(
            'categories', 'topCategories'
        ));
    }

    // Store new category
    public function storeCategory(
        CategoryRequest $request
    ) {
        Category::create([
            'business_id' => $this->businessId(),
            'parent_id'   => $request->parent_id,
            'name'        => $request->name,
            'description' => $request->description,
        ]);

        return redirect()
            ->route('inventory.categories')
            ->with('success', 
                'Category created successfully.');
    }

    // Update category
    public function updateCategory(
        CategoryRequest $request, 
        Category $category
    ) {
        $this->authorizeCategory($category);

        $category->update($request->validated());

        return redirect()
            ->route('inventory.categories')
            ->with('success', 
                'Category updated successfully.');
    }

    // Delete category
    public function destroyCategory(Category $category) {
        $this->authorizeCategory($category);

        // Unlink products before deleting
        Product::where('category_id', $category->id)
            ->update(['category_id' => null]);

        $category->delete();

        return redirect()
            ->route('inventory.categories')
            ->with('success', 
                'Category deleted. Products unlinked.');
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    //  AUTHORIZATION HELPERS
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

    // Make sure product belongs to this business
    private function authorizeProduct(
        Product $product
    ): void {
        if ($product->business_id !== $this->businessId()) {
            abort(403, 'Unauthorized action.');
        }
    }

    // Make sure category belongs to this business
    private function authorizeCategory(
        Category $category
    ): void {
        if ($category->business_id !== $this->businessId()) {
            abort(403, 'Unauthorized action.');
        }
    }
}
