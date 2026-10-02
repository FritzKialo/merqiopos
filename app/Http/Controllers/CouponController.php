<?php
namespace App\Http\Controllers;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CouponController extends Controller {
    private function businessId() { return Auth::user()->currentBusiness()->id; }

    public function index() {
        $coupons = Coupon::where('business_id', $this->businessId())
            ->withCount('usages')
            ->latest()->paginate(20);
        return view('coupons.index', compact('coupons'));
    }

    public function create() {
        return view('coupons.create');
    }

    public function store(Request $request) {
        $businessId = $this->businessId();
        $request->validate([
            'code' => ['required','string','max:50', \Illuminate\Validation\Rule::unique('coupons')->where('business_id', $businessId)],
            'name' => 'required|string|max:200',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
        ]);
        Coupon::create([
            'business_id' => $businessId,
            'code' => strtoupper($request->code),
            'name' => $request->name,
            'discount_type' => $request->discount_type,
            'discount_value' => $request->discount_value,
            'min_order_amount' => $request->min_order_amount ?? 0,
            'max_uses' => $request->max_uses,
            'is_active' => $request->boolean('is_active', true),
            'starts_at' => $request->starts_at,
            'expires_at' => $request->expires_at,
        ]);
        return redirect()->route('coupons.index')->with('success', 'Coupon created.');
    }

    public function edit(Coupon $coupon) {
        abort_if($coupon->business_id !== $this->businessId(), 403);
        return view('coupons.edit', compact('coupon'));
    }

    public function update(Request $request, Coupon $coupon) {
        abort_if($coupon->business_id !== $this->businessId(), 403);
        $businessId = $this->businessId();
        $request->validate([
            'code' => ['required','string','max:50', \Illuminate\Validation\Rule::unique('coupons')->where('business_id', $businessId)->ignore($coupon->id)],
            'name' => 'required|string|max:200',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
        ]);
        $coupon->update([
            'code' => strtoupper($request->code),
            'name' => $request->name,
            'discount_type' => $request->discount_type,
            'discount_value' => $request->discount_value,
            'min_order_amount' => $request->min_order_amount ?? 0,
            'max_uses' => $request->max_uses,
            'is_active' => $request->boolean('is_active', true),
            'starts_at' => $request->starts_at,
            'expires_at' => $request->expires_at,
        ]);
        return redirect()->route('coupons.index')->with('success', 'Coupon updated.');
    }

    public function destroy(Coupon $coupon) {
        abort_if($coupon->business_id !== $this->businessId(), 403);
        $coupon->delete();
        return redirect()->route('coupons.index')->with('success', 'Deleted.');
    }

    public function validateCoupon(Request $request) {
        $request->validate([
            'code' => 'required|string',
            'amount' => 'nullable|numeric|min:0',
        ]);
        // Never trust a client-supplied business_id here — this is an
        // authenticated staff endpoint (role:owner,manager), but that only
        // checks the ROLE, not which business the caller is scoped to. A
        // client-supplied business_id previously let any owner/manager probe
        // OTHER businesses' coupons (existence + discount amount) just by
        // guessing a business_id and a plausible code, the same class of
        // cross-tenant leak already fixed on the shop side (see
        // Shop\StoreController::applyCoupon()'s doc comment).
        $coupon = Coupon::where('business_id', $this->businessId())
            ->where('code', strtoupper($request->code))
            ->first();
        if (!$coupon) {
            return response()->json(['valid' => false, 'message' => 'Coupon code not found.']);
        }
        $amount = (float)($request->amount ?? 0);
        if (!$coupon->isValid($amount)) {
            $msg = 'This coupon is not valid.';
            if ($coupon->expires_at && $coupon->expires_at->isPast()) $msg = 'This coupon has expired.';
            elseif ($coupon->max_uses !== null && $coupon->effectiveUses() >= $coupon->max_uses) $msg = 'This coupon has reached its usage limit.';
            elseif ($amount < $coupon->min_order_amount) $msg = 'Minimum order of KSh ' . number_format($coupon->min_order_amount, 2) . ' required.';
            return response()->json(['valid' => false, 'message' => $msg]);
        }
        $discount = $coupon->calculateDiscount($amount);
        return response()->json([
            'valid' => true,
            'discount' => $discount,
            'message' => 'Coupon applied! You save KSh ' . number_format($discount, 2),
            'coupon_code' => $coupon->code,
        ]);
    }
}
