<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductBatchController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    private function authorizeProduct(Product $product): void {
        if ($product->business_id !== $this->businessId()) abort(403);
    }

    public function index(Product $product) {
        $this->authorizeProduct($product);

        $batches = ProductBatch::where('product_id', $product->id)
            ->with('supplier', 'variant')
            ->orderBy('expiry_date')
            ->get();

        return view('inventory.batches.index', compact('product', 'batches'));
    }

    public function store(Product $product, Request $request) {
        $this->authorizeProduct($product);

        $businessId = $this->businessId();

        $data = $request->validate([
            'batch_number'  => 'required|string|max:100',
            'expiry_date'   => 'nullable|date',
            'quantity'      => 'required|numeric|min:0',
            'cost_price'    => 'nullable|numeric|min:0',
            // Unscoped exists: previously — could link this batch to
            // another business's supplier record (leaked via ->supplier on
            // the batches index).
            'supplier_id'   => ['nullable', \Illuminate\Validation\Rule::exists('suppliers', 'id')->where('business_id', $businessId)],
            'received_date' => 'required|date',
            'notes'         => 'nullable|string',
            // Same for variant_id — must belong to this exact product, not
            // just any product_variants row.
            'variant_id'    => ['nullable', \Illuminate\Validation\Rule::exists('product_variants', 'id')->where('product_id', $product->id)],
        ]);

        DB::transaction(function () use ($data, $product) {
            $data['business_id'] = $this->businessId();
            $data['product_id']  = $product->id;
            ProductBatch::create($data);
            $product->increment('stock_qty', $data['quantity']);
        });

        return back()->with('success', 'Batch added and stock updated.');
    }

    public function destroy(Product $product, ProductBatch $batch) {
        $this->authorizeProduct($product);
        if ($batch->product_id !== $product->id) abort(403);

        DB::transaction(function () use ($product, $batch) {
            $product->decrement('stock_qty', $batch->quantity);
            $batch->delete();
        });

        return back()->with('success', 'Batch removed and stock decremented.');
    }
}
