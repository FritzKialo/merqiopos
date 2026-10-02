<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductVariantController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    private function authorizeProduct(Product $product): void {
        if ($product->business_id !== $this->businessId()) {
            abort(403);
        }
    }

    public function index(Product $product) {
        $this->authorizeProduct($product);
        $variants = $product->variants()->get();
        return view('inventory.variants.index', compact('product', 'variants'));
    }

    public function store(Product $product, Request $request) {
        $this->authorizeProduct($product);

        $data = $request->validate([
            'name'         => 'required|string|max:100',
            'sku'          => 'nullable|string|max:100|unique:product_variants,sku',
            'barcode'      => 'nullable|string|max:100',
            'price'        => 'nullable|numeric|min:0',
            'cost_price'   => 'nullable|numeric|min:0',
            'stock_qty'    => 'required|numeric|min:0',
            'reorder_level'=> 'nullable|numeric|min:0',
            'sort_order'   => 'nullable|integer',
        ]);

        $product->variants()->create($data);
        $product->update(['has_variants' => true]);

        return back()->with('success', 'Variant added successfully.');
    }

    public function update(Product $product, ProductVariant $variant, Request $request) {
        $this->authorizeProduct($product);

        if ($variant->product_id !== $product->id) abort(403);

        $data = $request->validate([
            'name'         => 'required|string|max:100',
            'sku'          => 'nullable|string|max:100|unique:product_variants,sku,' . $variant->id,
            'barcode'      => 'nullable|string|max:100',
            'price'        => 'nullable|numeric|min:0',
            'cost_price'   => 'nullable|numeric|min:0',
            'stock_qty'    => 'required|numeric|min:0',
            'reorder_level'=> 'nullable|numeric|min:0',
            'is_active'    => 'boolean',
            'sort_order'   => 'nullable|integer',
        ]);

        $variant->update($data);

        return back()->with('success', 'Variant updated.');
    }

    public function destroy(Product $product, ProductVariant $variant) {
        $this->authorizeProduct($product);

        if ($variant->product_id !== $product->id) abort(403);

        $variant->delete();

        // If no variants remain, clear the flag
        if ($product->variants()->count() === 0) {
            $product->update(['has_variants' => false]);
        }

        return back()->with('success', 'Variant deleted.');
    }
}
