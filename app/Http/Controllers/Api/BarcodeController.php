<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BarcodeController extends Controller {
    public function lookup(Request $request) {
        $q = $request->get('q', '');
        if (strlen($q) < 1) return response()->json(['results' => []]);
        $businessId = Auth::user()->currentBusiness()->id;
        $product = Product::where('business_id', $businessId)
            ->where(function($query) use ($q) {
                $query->where('barcode', $q)
                      ->orWhere('sku', $q)
                      ->orWhere('name', 'LIKE', "%{$q}%");
            })
            ->first();
        if (!$product) return response()->json(['found' => false]);
        return response()->json([
            'found' => true,
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->selling_price,
            'stock_qty' => $product->stock_qty,
            'barcode' => $product->barcode,
            'sku' => $product->sku,
        ]);
    }

    public function search(Request $request) {
        $q = $request->get('q', '');
        if (strlen($q) < 2) return response()->json([]);
        $businessId = Auth::user()->currentBusiness()->id;
        $products = Product::where('business_id', $businessId)
            ->where(function($query) use ($q) {
                $query->where('name', 'LIKE', "%{$q}%")
                      ->orWhere('barcode', 'LIKE', "%{$q}%")
                      ->orWhere('sku', 'LIKE', "%{$q}%");
            })
            ->limit(10)
            ->get(['id','name','selling_price','stock_qty','sku','barcode']);
        return response()->json($products);
    }
}
