<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoyaltyController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    public function tiers()
    {
        $business = Auth::user()->currentBusiness();
        $program = \App\Models\LoyaltyProgram::where('business_id', $business->id)->first();
        $tiers = $program ? ($program->tiers ?? []) : [];
        $defaultTiers = [
            ['name'=>'Bronze','min_points'=>0,'multiplier'=>1,'discount_pct'=>0,'perks'=>'Basic membership'],
            ['name'=>'Silver','min_points'=>1000,'multiplier'=>1.5,'discount_pct'=>5,'perks'=>'5% discount'],
            ['name'=>'Gold','min_points'=>5000,'multiplier'=>2,'discount_pct'=>10,'perks'=>'10% discount'],
        ];
        if (empty($tiers)) $tiers = $defaultTiers;
        return view('settings.loyalty-tiers', compact('program', 'tiers'));
    }

    public function saveTiers(\Illuminate\Http\Request $request)
    {
        $business = Auth::user()->currentBusiness();
        $program = \App\Models\LoyaltyProgram::where('business_id', $business->id)->firstOrCreate(
            ['business_id' => $business->id],
            ['name' => 'Default', 'points_per_shilling' => 1, 'is_active' => true]
        );
        $tiers = [];
        if ($request->tier_name) {
            foreach ($request->tier_name as $i => $name) {
                if ($name) $tiers[] = [
                    'name' => $name,
                    'min_points' => (int)($request->tier_min_points[$i] ?? 0),
                    'multiplier' => (float)($request->tier_multiplier[$i] ?? 1),
                    'discount_pct' => (float)($request->tier_discount[$i] ?? 0),
                    'perks' => $request->tier_perks[$i] ?? '',
                ];
            }
        }
        $program->update(['tiers' => $tiers]);
        return back()->with('success', 'Loyalty tiers saved.');
    }

    public function customerPoints(Customer $customer) {
        $businessId = $this->businessId();
        abort_if($customer->business_id !== $businessId, 403);

        $program = LoyaltyProgram::where('business_id', $businessId)->first();

        $transactions = LoyaltyTransaction::where('customer_id', $customer->id)
            ->forBusiness($businessId)
            ->with('sale')
            ->latest()
            ->paginate(20);

        return view('loyalty.customer', compact('customer', 'program', 'transactions'));
    }
}
