<?php

namespace App\Http\Controllers;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffController extends Controller
{
    private function business()
    {
        return Auth::user()->currentBusiness();
    }

    private function authorizeStaff(User $user): void
    {
        $business = $this->business();
        $assigned = $user->businesses()->where('business_id', $business->id)->exists();
        if (!$assigned) abort(403);
    }

    // ── Index — all staff with profile status ─────────────────────────────────

    public function index()
    {
        $business = $this->business();

        $staff = $business->users()
            ->with(['staffProfiles' => fn ($q) => $q->where('business_id', $business->id)])
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($business) {
                $user->hr_profile = $user->staffProfiles
                    ->firstWhere('business_id', $business->id);
                return $user;
            });

        return view('staff.index', compact('staff', 'business'));
    }

    // ── Show one staff member ─────────────────────────────────────────────────

    public function show(User $user)
    {
        $this->authorizeStaff($user);

        $business = $this->business();
        $profile  = $user->staffProfileFor($business->id);

        $recentItems = \App\Models\PayrollItem::where('user_id', $user->id)
            ->where('business_id', $business->id)
            ->with('period')
            ->latest()
            ->take(6)
            ->get();

        return view('staff.show', compact('user', 'business', 'profile', 'recentItems'));
    }

    // ── Create / edit profile ─────────────────────────────────────────────────

    public function editProfile(User $user)
    {
        $this->authorizeStaff($user);

        $business = $this->business();
        $profile  = $user->staffProfileFor($business->id)
            ?? new StaffProfile(['user_id' => $user->id, 'business_id' => $business->id]);

        $payTypes       = StaffProfile::payTypes();
        $payrollEnabled = $business->isPayrollEnabled();

        return view('staff.profile', compact(
            'user', 'business', 'profile', 'payTypes', 'payrollEnabled'
        ));
    }

    public function updateProfile(Request $request, User $user)
    {
        $this->authorizeStaff($user);

        $business = $this->business();

        $request->validate([
            'pay_type'         => 'required|in:retainer,commission,hybrid',
            'retainer_amount'  => 'required_if:pay_type,retainer,hybrid|nullable|numeric|min:0',
            'commission_rate'  => 'required_if:pay_type,commission,hybrid|nullable|numeric|min:0|max:100',
            'kra_pin'          => 'nullable|string|max:20',
            'nssf_no'          => 'nullable|string|max:20',
            'shif_no'          => 'nullable|string|max:20',
            'id_number'        => 'nullable|string|max:20',
            'job_title'        => 'nullable|string|max:100',
            'department'       => 'nullable|string|max:100',
            'employment_date'  => 'nullable|date|before_or_equal:today',
            'termination_date' => 'nullable|date|after_or_equal:employment_date',
            // Per-employee deduction overrides (checkboxes)
            'override_paye'    => 'nullable|boolean',
            'override_nssf'    => 'nullable|boolean',
            'override_shif'    => 'nullable|boolean',
            'use_overrides'    => 'nullable|boolean',
            'credit_limit'     => 'nullable|numeric|min:0',
            // Array form: the pattern contains a '|', which the pipe-delimited
            // string form would split apart.
            'mpesa_phone'      => ['nullable', 'string', 'max:20', 'regex:/^(?:\+?254|0)?[71]\d{8}$/'],
        ]);

        // Build deduction_overrides only if the user explicitly opted in
        $deductionOverrides = null;
        if ($request->boolean('use_overrides')) {
            $deductionOverrides = [
                'paye' => $request->boolean('override_paye'),
                'nssf' => $request->boolean('override_nssf'),
                'shif' => $request->boolean('override_shif'),
                'housing_levy' => $request->boolean('override_housing_levy'),
            ];
        }

        $profileData = [
                'pay_type'            => $request->pay_type,
                'retainer_amount'     => $request->retainer_amount ?? 0,
                'commission_rate'     => $request->commission_rate ?? 0,
                'kra_pin'             => $request->kra_pin,
                'nssf_no'             => $request->nssf_no,
                'shif_no'             => $request->shif_no,
                'id_number'           => $request->id_number,
                'job_title'           => $request->job_title,
                'department'          => $request->department,
                'employment_date'     => $request->employment_date,
                'termination_date'    => $request->termination_date,
                'deduction_overrides' => $deductionOverrides,
                'credit_limit'        => $request->credit_limit,
        ];
        // Only written once the column exists (older databases don't have it yet).
        if (\Illuminate\Support\Facades\Schema::hasColumn('staff_profiles', 'mpesa_phone')) {
            $profileData['mpesa_phone'] = $request->filled('mpesa_phone') ? preg_replace('/[\s-]/', '', $request->mpesa_phone) : null;
        }

        StaffProfile::updateOrCreate(
            ['user_id' => $user->id, 'business_id' => $business->id],
            $profileData
        );

        return redirect()
            ->route('staff.show', $user)
            ->with('success', $user->name . '\'s payroll profile has been saved.');
    }
}
