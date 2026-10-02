<?php

namespace App\Http\Controllers;

use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductPriceTier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PriceTierController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    private function authorize(PriceTier $tier): void {
        if ($tier->business_id !== $this->businessId()) abort(403);
    }

    public function index() {
        $businessId = $this->businessId();
        $tiers      = PriceTier::forBusiness($businessId)->with('customers')->get();
        return view('price-tiers.index', compact('tiers'));
    }

    public function store(Request $request) {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_default'  => 'boolean',
        ]);
        $data['business_id'] = $this->businessId();

        DB::transaction(function () use ($data) {
            if (!empty($data['is_default'])) {
                PriceTier::forBusiness($data['business_id'])->update(['is_default' => false]);
            }
            PriceTier::create($data);
        });

        return back()->with('success', 'Price tier created.');
    }

    public function update(Request $request, PriceTier $priceTier) {
        $this->authorize($priceTier);
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_default'  => 'boolean',
        ]);

        DB::transaction(function () use ($data, $priceTier) {
            if (!empty($data['is_default'])) {
                PriceTier::forBusiness($priceTier->business_id)->update(['is_default' => false]);
            }
            $priceTier->update($data);
        });

        return back()->with('success', 'Price tier updated.');
    }

    public function destroy(PriceTier $priceTier) {
        $this->authorize($priceTier);
        $priceTier->delete();
        return back()->with('success', 'Price tier deleted.');
    }

    // Set tier price overrides for a product
    public function updateProductPrices(Product $product, Request $request) {
        if ($product->business_id !== $this->businessId()) abort(403);

        $businessId = $this->businessId();

        // The product-edit form submits one row per tier; a blank price means
        // "no override — use the normal selling price", so split those out
        // before validation (which requires a price on every row it sees).
        $rows     = collect($request->input('prices', []));
        $isBlank  = fn ($r) => trim((string) ($r['price'] ?? '')) === '';
        $clearIds = $rows->filter($isBlank)->pluck('tier_id')->filter()->all();
        $request->merge(['prices' => $rows->reject($isBlank)->values()->all()]);

        $request->validate([
            'prices'          => 'array',
            // Was just 'exists:price_tiers,id' — checks the tier exists
            // ANYWHERE on the platform, not that it belongs to this
            // business. A product's price override could get linked to a
            // completely different business's price tier.
            'prices.*.tier_id'=> ['required', \Illuminate\Validation\Rule::exists('price_tiers', 'id')->where('business_id', $businessId)],
            'prices.*.price'  => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($product, $request, $clearIds, $businessId) {
            if ($clearIds) {
                ProductPriceTier::where('product_id', $product->id)
                    ->whereIn('price_tier_id', PriceTier::forBusiness($businessId)->whereIn('id', $clearIds)->pluck('id'))
                    ->delete();
            }
            foreach ($request->prices ?? [] as $row) {
                ProductPriceTier::updateOrCreate(
                    ['price_tier_id' => $row['tier_id'], 'product_id' => $product->id],
                    ['price' => $row['price']]
                );
            }
        });

        return back()->with('success', 'Prices updated.');
    }
}
