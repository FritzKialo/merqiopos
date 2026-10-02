<?php

namespace App\Http\Controllers;

use App\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DiscountController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    public function index() {
        $businessId = $this->businessId();
        $discounts  = Discount::forBusiness($businessId)->latest()->get();
        return view('discounts.index', compact('discounts'));
    }

    public function create() {
        return view('discounts.create');
    }

    public function store(Request $request) {
        $data = $this->validated($request);
        $data['business_id'] = $this->businessId();
        Discount::create($data);
        return redirect()->route('discounts.index')->with('success', 'Discount created.');
    }

    public function edit(Discount $discount) {
        $this->authorize($discount);
        return view('discounts.edit', compact('discount'));
    }

    public function update(Request $request, Discount $discount) {
        $this->authorize($discount);
        $discount->update($this->validated($request, $discount->id));
        return redirect()->route('discounts.index')->with('success', 'Discount updated.');
    }

    public function destroy(Discount $discount) {
        $this->authorize($discount);
        $discount->delete();
        return back()->with('success', 'Discount deleted.');
    }

    public function toggle(Discount $discount) {
        $this->authorize($discount);
        $discount->update(['is_active' => !$discount->is_active]);
        return back()->with('success', $discount->is_active ? 'Discount enabled.' : 'Discount disabled.');
    }

    // AJAX: apply discount code
    public function applyDiscount(Request $request) {
        $request->validate([
            'code'         => 'required|string',
            'order_amount' => 'required|numeric|min:0',
        ]);

        $businessId = $this->businessId();
        $discount   = Discount::forBusiness($businessId)
            ->where('code', $request->code)
            ->first();

        if (!$discount || !$discount->isValid()) {
            return response()->json(['error' => 'Invalid or expired discount code.'], 422);
        }

        $amount = $discount->calculate((float) $request->order_amount);

        if ($amount <= 0) {
            return response()->json(['error' => 'Order does not meet the minimum amount for this discount.'], 422);
        }

        return response()->json([
            'discount_amount' => $amount,
            'discount_id'     => $discount->id,
            'message'         => "Discount \"{$discount->name}\" applied: KSh " . number_format($amount, 2),
        ]);
    }

    private function authorize(Discount $discount): void {
        if ($discount->business_id !== $this->businessId()) abort(403);
    }

    private function validated(Request $request, ?int $ignoreId = null): array {
        return $request->validate([
            'name'             => 'required|string|max:100',
            'code'             => 'nullable|string|max:50|unique:discounts,code' . ($ignoreId ? ",{$ignoreId}" : ''),
            'type'             => 'required|in:percentage,fixed',
            'value'            => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_uses'         => 'nullable|integer|min:1',
            'valid_from'       => 'nullable|date',
            'valid_until'      => 'nullable|date|after_or_equal:valid_from',
            'is_active'        => 'boolean',
        ]);
    }
}
