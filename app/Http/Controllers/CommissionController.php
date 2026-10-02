<?php
namespace App\Http\Controllers;

use App\Models\CommissionEarning;
use App\Models\CommissionRule;
use App\Models\Sale;
use App\Models\StaffProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommissionController extends Controller
{
    private function businessId(): int
    {
        return Auth::user()->currentBusiness()->id;
    }

    // ── Rules ─────────────────────────────────────────────────────────────────

    public function index()
    {
        $rules = CommissionRule::forBusiness()
            ->with('staffProfile.user')
            ->latest()
            ->paginate(20);

        return view('staff.commissions.index', compact('rules'));
    }

    public function create()
    {
        $staffProfiles = StaffProfile::where('business_id', $this->businessId())
            ->with('user')
            ->get();

        return view('staff.commissions.create', compact('staffProfiles'));
    }

    public function store(Request $request)
    {
        $businessId = $this->businessId();

        // Unscoped exists: previously — could link this commission rule to
        // another business's staff profile.
        $data = $request->validate([
            'staff_profile_id' => ['required', \Illuminate\Validation\Rule::exists('staff_profiles', 'id')->where('business_id', $businessId)],
            'rule_type'        => 'required|in:percentage,flat_per_sale',
            'rate'             => 'required|numeric|min:0',
            'min_sales_amount' => 'nullable|numeric|min:0',
            'is_active'        => 'boolean',
        ]);

        $data['business_id'] = $businessId;
        $data['is_active']   = $request->boolean('is_active', true);

        CommissionRule::create($data);

        return redirect()->route('staff.commissions.index')
            ->with('success', 'Commission rule created.');
    }

    public function update(Request $request, CommissionRule $rule)
    {
        abort_unless($rule->business_id === $this->businessId(), 403);

        $data = $request->validate([
            'rule_type'        => 'required|in:percentage,flat_per_sale',
            'rate'             => 'required|numeric|min:0',
            'min_sales_amount' => 'nullable|numeric|min:0',
            'is_active'        => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $rule->update($data);

        return back()->with('success', 'Commission rule updated.');
    }

    public function destroy(CommissionRule $rule)
    {
        abort_unless($rule->business_id === $this->businessId(), 403);
        $rule->delete();

        return back()->with('success', 'Commission rule deleted.');
    }

    // ── Earnings ──────────────────────────────────────────────────────────────

    public function earnings(Request $request)
    {
        $month = (int) $request->get('month', now()->month);
        $year  = (int) $request->get('year', now()->year);
        $staffId = $request->get('staff_profile_id');

        $query = CommissionEarning::forBusiness()
            ->with('staffProfile.user')
            ->where('period_month', $month)
            ->where('period_year', $year);

        if ($staffId) {
            $query->where('staff_profile_id', $staffId);
        }

        $earnings      = $query->latest()->paginate(25)->withQueryString();
        $staffProfiles = StaffProfile::where('business_id', $this->businessId())
            ->with('user')->get();

        return view('staff.commissions.earnings', compact('earnings', 'month', 'year', 'staffProfiles', 'staffId'));
    }

    public function calculate(Request $request)
    {
        $data = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year'  => 'required|integer|min:2020',
        ]);

        $businessId = $this->businessId();
        $month      = (int) $data['month'];
        $year       = (int) $data['year'];

        $rules = CommissionRule::forBusiness($businessId)->active()->with('staffProfile')->get();

        $start = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfDay();
        $end   = $start->copy()->endOfMonth()->endOfDay();

        $created = 0;

        foreach ($rules as $rule) {
            $profile = $rule->staffProfile;
            if (!$profile) continue;

            $grossSales = (float) Sale::where('user_id', $profile->user_id)
                ->where('business_id', $businessId)
                ->whereBetween('created_at', [$start, $end])
                ->sum('total_amount');

            // Skip if below minimum sales threshold
            if ($rule->min_sales_amount !== null && $grossSales < (float) $rule->min_sales_amount) {
                continue;
            }

            $commission = $rule->rule_type === 'percentage'
                ? round($grossSales * (float) $rule->rate / 100, 2)
                : round((float) $rule->rate, 2); // flat per rule per period

            CommissionEarning::updateOrCreate(
                [
                    'business_id'      => $businessId,
                    'staff_profile_id' => $rule->staff_profile_id,
                    'commission_rule_id' => $rule->id,
                    'period_month'     => $month,
                    'period_year'      => $year,
                ],
                [
                    'gross_sales'       => $grossSales,
                    'commission_amount' => $commission,
                    'status'            => 'pending',
                ]
            );

            $created++;
        }

        return redirect()->route('staff.commissions.earnings', compact('month', 'year'))
            ->with('success', "Commissions calculated: {$created} record(s) updated.");
    }

    public function approve(CommissionEarning $earning)
    {
        abort_unless($earning->business_id === $this->businessId(), 403);
        // Same missing-role-check bug class as ExpenseClaimController/
        // PurchaseRequisitionController — "approve" implies a manager
        // decision, but nothing stopped any staff member (including the
        // earning staff member themselves) from approving their own
        // commission earning.
        abort_unless(Auth::user()->hasAnyRole('owner', 'manager', 'overall_manager'), 403);
        $earning->update(['status' => 'approved']);

        return back()->with('success', 'Commission earning approved.');
    }
}
