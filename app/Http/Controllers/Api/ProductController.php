<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    private function business(Request $request)
    {
        return $request->get('_api_business');
    }

    public function index(Request $request)
    {
        $business = $this->business($request);
        $query = Product::with('category')->forBusiness($business->id);

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($qb) use ($q) {
                $qb->where('name', 'like', "%{$q}%")
                   ->orWhere('sku',  'like', "%{$q}%")
                   ->orWhere('barcode', 'like', "%{$q}%");
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->boolean('low_stock')) {
            $query->lowStock();
        }

        $products = $query->orderBy('name')->paginate(50);

        return response()->json([
            'data'  => $products->items(),
            'meta'  => [
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'per_page'     => $products->perPage(),
                'total'        => $products->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id)
    {
        $product = Product::with('category')
            ->forBusiness($this->business($request)->id)
            ->findOrFail($id);

        return response()->json(['data' => $product]);
    }

    public function store(Request $request)
    {
        $business = $this->business($request);

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'sku'           => 'nullable|string|max:100',
            'barcode'       => 'nullable|string|max:100',
            'buying_price'  => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'stock_qty'     => 'nullable|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'unit'          => 'nullable|string|max:50',
            'status'        => 'nullable|in:active,inactive',
            'description'   => 'nullable|string',
            'category_id'   => 'nullable|exists:categories,id',
        ]);

        $validated['business_id'] = $business->id;
        if (empty($validated['sku'])) {
            $validated['sku'] = Product::generateSku($validated['name'], $business->id);
        }

        $product = Product::create($validated);

        return response()->json(['data' => $product->load('category'), 'message' => 'Product created.'], 201);
    }

    public function update(Request $request, int $id)
    {
        $product = Product::forBusiness($this->business($request)->id)->findOrFail($id);

        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'sku'           => 'sometimes|nullable|string|max:100',
            'barcode'       => 'sometimes|nullable|string|max:100',
            'buying_price'  => 'sometimes|numeric|min:0',
            'selling_price' => 'sometimes|numeric|min:0',
            'stock_qty'     => 'sometimes|integer|min:0',
            'reorder_level' => 'sometimes|integer|min:0',
            'unit'          => 'sometimes|nullable|string|max:50',
            'status'        => 'sometimes|in:active,inactive',
            'description'   => 'sometimes|nullable|string',
            'category_id'   => 'sometimes|nullable|exists:categories,id',
        ]);

        $product->update($validated);

        return response()->json(['data' => $product->load('category'), 'message' => 'Product updated.']);
    }
}
