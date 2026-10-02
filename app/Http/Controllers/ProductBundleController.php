<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductBundleController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    private function authorize(ProductBundle $bundle): void {
        if ($bundle->business_id !== $this->businessId()) abort(403);
    }

    public function index() {
        $businessId = $this->businessId();
        $bundles    = ProductBundle::forBusiness($businessId)->withCount('items')->latest()->get();
        return view('bundles.index', compact('bundles'));
    }

    public function create() {
        $businessId = $this->businessId();
        $products   = Product::forBusiness($businessId)->active()->orderBy('name')->get(['id','name','selling_price']);
        return view('bundles.create', compact('products'));
    }

    public function store(Request $request) {
        $data = $this->validated($request);
        $items = $request->input('items', []);

        DB::transaction(function () use ($data, $items) {
            $data['business_id'] = $this->businessId();
            $bundle = ProductBundle::create($data);
            $this->syncItems($bundle, $items);
        });

        return redirect()->route('bundles.index')->with('success', 'Bundle created.');
    }

    public function edit(ProductBundle $bundle) {
        $this->authorize($bundle);
        $bundle->load('items.product', 'items.variant');
        $businessId = $this->businessId();
        $products   = Product::forBusiness($businessId)->active()->orderBy('name')->get(['id','name','selling_price']);
        return view('bundles.edit', compact('bundle', 'products'));
    }

    public function update(Request $request, ProductBundle $bundle) {
        $this->authorize($bundle);
        $data  = $this->validated($request);
        $items = $request->input('items', []);

        DB::transaction(function () use ($data, $items, $bundle) {
            $bundle->update($data);
            $bundle->items()->delete();
            $this->syncItems($bundle, $items);
        });

        return redirect()->route('bundles.index')->with('success', 'Bundle updated.');
    }

    public function destroy(ProductBundle $bundle) {
        $this->authorize($bundle);
        $bundle->delete();
        return back()->with('success', 'Bundle deleted.');
    }

    private function validated(Request $request): array {
        return $request->validate([
            'name'        => 'required|string|max:255',
            'sku'         => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'is_active'   => 'boolean',
        ]);
    }

    private function syncItems(ProductBundle $bundle, array $items): void {
        foreach ($items as $row) {
            if (empty($row['product_id']) || empty($row['quantity'])) continue;
            ProductBundleItem::create([
                'product_bundle_id' => $bundle->id,
                'product_id'        => $row['product_id'],
                'variant_id'        => $row['variant_id'] ?? null,
                'quantity'          => $row['quantity'],
            ]);
        }
    }
}
