<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SerialNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SerialNumberController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    private function authorizeProduct(Product $product): void {
        if ($product->business_id !== $this->businessId()) abort(403);
    }

    public function index(Product $product, Request $request) {
        $this->authorizeProduct($product);

        $query  = SerialNumber::where('product_id', $product->id)
            ->forBusiness($this->businessId())
            ->with('sale');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $serials = $query->latest()->paginate(50)->withQueryString();
        $status  = $request->status ?? 'all';

        return view('inventory.serials.index', compact('product', 'serials', 'status'));
    }

    public function store(Product $product, Request $request) {
        $this->authorizeProduct($product);

        $request->validate([
            'serials'       => 'required|string',
            'received_date' => 'nullable|date',
            // Unscoped exists: previously — could attach these serials to a
            // variant belonging to a completely different product.
            'variant_id'    => ['nullable', \Illuminate\Validation\Rule::exists('product_variants', 'id')->where('product_id', $product->id)],
        ]);

        $businessId    = $this->businessId();
        $lines         = array_filter(array_map('trim', explode("\n", $request->serials)));
        $receivedDate  = $request->received_date ?? now()->toDateString();
        $added         = 0;
        $skipped       = [];

        DB::transaction(function () use ($lines, $businessId, $product, $receivedDate, $request, &$added, &$skipped) {
            foreach ($lines as $sn) {
                if (empty($sn)) continue;
                $exists = SerialNumber::where('business_id', $businessId)
                    ->where('serial_number', $sn)->exists();
                if ($exists) {
                    $skipped[] = $sn;
                    continue;
                }
                SerialNumber::create([
                    'business_id'   => $businessId,
                    'product_id'    => $product->id,
                    'variant_id'    => $request->variant_id,
                    'serial_number' => $sn,
                    'status'        => 'in_stock',
                    'received_date' => $receivedDate,
                ]);
                $added++;
            }
        });

        $msg = "{$added} serial number(s) added.";
        if ($skipped) $msg .= ' Skipped duplicates: ' . implode(', ', $skipped);

        return back()->with('success', $msg);
    }

    public function update(Product $product, SerialNumber $serial, Request $request) {
        $this->authorizeProduct($product);
        if ($serial->product_id !== $product->id) abort(403);

        $data = $request->validate([
            'status' => 'required|in:in_stock,sold,returned,scrapped',
            'notes'  => 'nullable|string',
        ]);

        $serial->update($data);

        return back()->with('success', 'Serial number updated.');
    }
}
