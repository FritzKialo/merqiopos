<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PromoCodeController extends Controller
{
    public function index()
    {
        $promoCodes = PromoCode::with('creator')
            ->withCount('redemptions')
            ->latest()
            ->paginate(25);

        return view('admin.promo-codes.index', compact('promoCodes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code'             => 'required|string|max:40|unique:promo_codes,code',
            'discount_type'    => 'required|in:percentage,fixed',
            'discount_value'   => 'required|numeric|min:0.01',
            'applicable_plans' => 'nullable|array',
            'applicable_plans.*' => 'in:solo,growth,enterprise',
            'max_redemptions'  => 'nullable|integer|min:1',
            'expires_at'       => 'nullable|date|after:now',
            'description'      => 'nullable|string|max:255',
        ]);

        if ($request->discount_type === 'percentage' && $request->discount_value > 100) {
            return back()->withInput()->with('error', 'A percentage discount cannot exceed 100%.');
        }

        $promoCode = PromoCode::create([
            'code'             => strtoupper(trim($request->code)),
            'discount_type'    => $request->discount_type,
            'discount_value'   => $request->discount_value,
            'applicable_plans' => $request->applicable_plans ?: null,
            'max_redemptions'  => $request->max_redemptions,
            'expires_at'       => $request->expires_at,
            'description'      => $request->description,
            'created_by'       => Auth::id(),
        ]);

        AuditLog::record('admin.promo_code.created', $promoCode, [
            'code' => $promoCode->code, 'discount_type' => $promoCode->discount_type, 'discount_value' => (float) $promoCode->discount_value,
        ]);

        return redirect()->route('admin.promo-codes.index')->with('success', "Promo code \"{$promoCode->code}\" created.");
    }

    public function toggle(PromoCode $promoCode)
    {
        $promoCode->update(['is_active' => ! $promoCode->is_active]);

        AuditLog::record($promoCode->is_active ? 'admin.promo_code.activated' : 'admin.promo_code.deactivated', $promoCode, ['code' => $promoCode->code]);

        return back()->with('success', "Promo code \"{$promoCode->code}\" " . ($promoCode->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function destroy(PromoCode $promoCode)
    {
        $code = $promoCode->code;
        $promoCode->delete();

        AuditLog::record('admin.promo_code.deleted', null, ['code' => $code]);

        return back()->with('success', "Promo code \"{$code}\" deleted.");
    }
}
