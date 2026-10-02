<?php

namespace App\Http\Controllers;

use App\Http\Requests\BusinessSettingsRequest;
use App\Http\Requests\PasswordChangeRequest;
use App\Http\Requests\TeamMemberRequest;
use App\Models\LoyaltyProgram;
use App\Models\User;
use App\Services\PromoCodeService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller {

    private function getBusiness() {
        return Auth::user()->currentBusiness();
    }

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    // ── Settings home ──────────────────────────
    public function index() {
        return redirect()->route('settings.business');
    }

    // ── Business profile ───────────────────────
    public function business() {
        $business = $this->getBusiness();     // ← updated call
        return view('settings.business', compact('business'));
    }

    public function updateBusiness(BusinessSettingsRequest $request) {
        $this->getBusiness()->update(         // ← updated call
            $request->validated()
        );

        return redirect()
            ->route('settings.business')
            ->with('success', 'Business profile updated.');
    }

    // ── Password ───────────────────────────────
    public function password() {
        return view('settings.password');
    }

    public function updatePassword(PasswordChangeRequest $request) {
        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);
        $user->endOtherSessions($request->session()->getId());

        return redirect()
            ->route('settings.password')
            ->with('success', 'Password changed successfully.');
    }

    // ── Team management ────────────────────────
    public function team() {
        $this->canManageTeam();

        $business = $this->getBusiness();
        $members = $business->users()
            ->where('users.id', '!=', Auth::id())
            ->orderBy('users.name')
            ->get();

        return view('settings.team', compact('members'));
    }

    public function storeMember(TeamMemberRequest $request) {
        $this->canManageTeam();
        $this->assertCanAssignRole($request->role);

        $org = $this->getBusiness()->organization;
        if ($org && !$org->canAddUser()) {
            $limit = $org->userLimit();
            // planName() returns "Trial" while on trial (by design, for
            // status banners) — that reads as nonsense here ("...limit on
            // the Trial plan"), so use the real underlying plan name instead.
            $planName    = $org->planConfig()['name'];
            $nextPlan    = $org->upgradePlan();
            $upgradeName = $nextPlan ? ucfirst($nextPlan) : 'a higher';

            return redirect()->route('settings.team')
                ->with('error', "You have reached the {$limit}-team-member limit on the {$planName} plan (across all your stores). Upgrade to {$upgradeName} to add more.");
        }

        $member = User::create([
            'name'            => $request->name,
            'email'           => $request->email,
            'password'        => Hash::make($request->password),
            'role'            => $request->role,
            // Org-level members (overall manager) need the org link for
            // cross-branch resolution; harmless for branch staff too.
            'organization_id' => $this->getBusiness()->organization_id,
            'is_active'       => true,
        ]);

        // The branch pivot only knows owner/manager/cashier; an overall manager
        // sits at 'manager' level within a branch while their global role grants
        // organization-wide power.
        $pivotRole = $request->role === 'overall_manager' ? 'manager' : $request->role;
        $this->getBusiness()->users()->attach($member->id, ['role' => $pivotRole]);

        \App\Models\AuditLog::record('team.member_added', $member, ['name' => $member->name, 'email' => $member->email, 'role' => $member->role]);

        return redirect()
            ->route('settings.team')
            ->with('success', $request->name . ' has been added to your team.');
    }

    public function editMember(User $user) {
        $this->canManageTeam();
        $this->authorizeMember($user);

        $business = $this->getBusiness();
        return view('settings.team', [
            'members'    => $business->users()
                ->where('users.id', '!=', Auth::id())
                ->orderBy('users.name')
                ->get(),
            'editMember' => $user,
        ]);
    }

    public function updateMember(TeamMemberRequest $request, User $user) {
        $this->canManageTeam();
        $this->authorizeMember($user);
        $this->assertCanAssignRole($request->role);

        $before = ['role' => $user->role, 'email' => $user->email, 'name' => $user->name];
        $data = [
            'name'            => $request->name,
            'email'           => $request->email,
            'role'            => $request->role,
            'organization_id' => $this->getBusiness()->organization_id,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);
        if ($request->filled('password')) {
            // The owner reset this person's password — end their open sessions.
            $user->endOtherSessions();
        }

        // The branch pivot holds the role that inter-branch transfer access is
        // decided by (managedBusinessIds() reads it). It was only written when
        // the member was created, so promoting someone left them without the
        // access and, worse, demoting a manager to cashier left their
        // manager-level transfer access in place.
        $pivotRole = $request->role === 'overall_manager' ? 'manager' : $request->role;
        $this->getBusiness()->users()->updateExistingPivot($user->id, ['role' => $pivotRole]);

        \App\Models\AuditLog::record('team.member_updated', $user, [
            'name' => $user->name,
            'role_from' => $before['role'], 'role_to' => $user->role,
            'email_changed' => $before['email'] !== $user->email,
            'password_reset' => $request->filled('password'),
        ]);

        return redirect()
            ->route('settings.team')
            ->with('success', $user->name . ' has been updated.');
    }

    public function toggleMember(User $user) {
        $this->canManageTeam();
        $this->authorizeMember($user);

        $user->update(['is_active' => !$user->is_active]);
        \App\Models\AuditLog::record('team.member_' . ($user->is_active ? 'activated' : 'deactivated'), $user, ['name' => $user->name, 'role' => $user->role]);

        $status = $user->is_active ? 'activated' : 'deactivated';

        return redirect()
            ->route('settings.team')
            ->with('success', $user->name . ' has been ' . $status . '.');
    }

    public function destroyMember(User $user) {
        $this->canManageTeam();
        $this->authorizeMember($user);

        $name = $user->name;
        \App\Models\AuditLog::record('team.member_removed', $user, ['name' => $user->name, 'email' => $user->email, 'role' => $user->role]);
        $user->delete();

        return redirect()
            ->route('settings.team')
            ->with('success', $name . ' has been removed.');
    }

    /**
     * The common real-world case: a staff member loses their phone (or
     * just the authenticator app on it) and has no recovery codes saved —
     * without this, there would be no way back in short of a platform
     * admin's intervention. Gated behind 'sudo' at the route level, same
     * as every other action here — the owner/manager's own identity is
     * re-confirmed before they can do this to someone else's account.
     */
    public function resetMemberTwoFactor(User $user) {
        $this->canManageTeam();
        $this->authorizeMember($user);

        $user->update([
            'google2fa_secret'          => null,
            'two_factor_enabled'        => false,
            'two_factor_confirmed_at'   => null,
            'two_factor_recovery_codes' => null,
        ]);

        \App\Models\AuditLog::record('team.member_2fa_reset', $user, ['name' => $user->name]);

        return redirect()
            ->route('settings.team')
            ->with('success', "Two-factor authentication has been reset for {$user->name}. They can sign in with just their password and set it up again.");
    }

    // ── Subscription ───────────────────────────
    public function subscription() {
        $organization = Auth::user()->organization;
        $plans        = config('plans.org');

        // Current usage — used to warn before switching to a plan with a
        // lower store/team limit than what's already in use. Downgrading
        // never deletes or deactivates existing stores/staff (they keep
        // working exactly as before), it only blocks creating MORE of them —
        // but that's easy to miss without a heads-up at the point of choice.
        $currentStoreCount = $organization ? $organization->businesses()->count() : 0;
        $currentUserCount  = $organization ? $organization->userCount() : 0;

        return view('settings.subscription', compact(
            'organization', 'plans', 'currentStoreCount', 'currentUserCount'
        ));
    }

    /**
     * AJAX preview for the subscription page's promo code field — validates
     * the code and returns the discounted price without charging anything.
     * The actual checkout (Paystack/M-Pesa) re-validates independently since
     * time/usage can pass between this preview and the real payment.
     */
    public function applyPromoCode(Request $request, PromoCodeService $promoCodeService) {
        $request->validate([
            'code' => 'required|string|max:40',
            'plan' => 'required|in:solo,growth,enterprise',
        ]);

        $organization = Auth::user()->organization;
        $plan         = config('plans.org.' . $request->plan);

        $result = $promoCodeService->validate($request->code, $request->plan, $organization, (float) $plan['price']);

        if (! $result['valid']) {
            return response()->json(['valid' => false, 'message' => $result['message']]);
        }

        return response()->json([
            'valid'            => true,
            'originalAmount'   => $result['originalAmount'],
            'discountAmount'   => $result['discountAmount'],
            'discountedAmount' => $result['discountedAmount'],
        ]);
    }

    // ── SMS Settings ───────────────────────────
    public function sms() {
        $this->ownerOnly();
        $business = $this->getBusiness();
        return view('settings.sms', compact('business'));
    }

    public function updateSms(Request $request) {
        $this->ownerOnly();
        $request->validate([
            'sms_provider'   => 'nullable|in:africas_talking,twilio',
            'sms_api_key'    => 'nullable|string|max:500',
            'sms_username'   => 'nullable|string|max:100',
            'sms_sender_id'  => 'nullable|string|max:20',
        ]);
        $business = $this->getBusiness();
        $data = $request->only(['sms_provider', 'sms_username', 'sms_sender_id']);
        if ($request->filled('sms_api_key')) {
            $data['sms_api_key'] = encrypt($request->sms_api_key);
        }
        $business->update($data);
        return redirect()->route('settings.sms')->with('success', 'SMS settings saved.');
    }

    public function testSms(Request $request) {
        $this->ownerOnly();
        $request->validate(['phone' => 'required|string']);
        $business = $this->getBusiness();
        $sms = \App\Services\SmsService::forBusiness($business);
        if (!$sms->isConfigured()) {
            return back()->with('error', 'SMS is not configured yet. Please save your credentials first.');
        }
        $sent = $sms->send($request->phone, 'Test SMS from ' . $business->name . ' via Merqio POS.');
        return back()->with($sent ? 'success' : 'error', $sent ? 'Test SMS sent successfully!' : 'Failed to send SMS. Check your credentials.');
    }

    // ── M-Pesa credentials ─────────────────────
    public function mpesa() {
        $this->ownerOnly();
        $business = $this->getBusiness();
        return view('settings.mpesa', compact('business'));
    }

    public function updateMpesa(Request $request) {
        $this->ownerOnly();

        $request->validate([
            'mpesa_shortcode'       => 'nullable|string|max:20',
            'mpesa_consumer_key'    => 'nullable|string|max:255',
            'mpesa_consumer_secret' => 'nullable|string|max:255',
            'mpesa_passkey'         => 'nullable|string|max:1000',
            'mpesa_till_number'     => 'nullable|string|max:20',
            'mpesa_environment'     => 'required|in:sandbox,production',
        ]);

        $business = $this->getBusiness();

        // Always update non-sensitive fields
        $data = $request->only(['mpesa_shortcode', 'mpesa_till_number', 'mpesa_environment']);

        // Only update sensitive credentials if a new value was provided
        // (blank = keep the existing encrypted value)
        if ($request->filled('mpesa_consumer_key')) {
            $data['mpesa_consumer_key'] = $request->mpesa_consumer_key;
        }
        if ($request->filled('mpesa_consumer_secret')) {
            $data['mpesa_consumer_secret'] = $request->mpesa_consumer_secret;
        }
        if ($request->filled('mpesa_passkey')) {
            $data['mpesa_passkey'] = $request->mpesa_passkey;
        }

        $business->update($data);

        // Daraja initiator used by "Verify with Safaricom" (Transaction Status).
        // The security credential is stored encrypted; blank keeps the saved one.
        if (\Illuminate\Support\Facades\Schema::hasColumn('businesses', 'mpesa_initiator_name')) {
            $init = ['mpesa_initiator_name' => $request->input('mpesa_initiator_name') ?: null];
            if ($request->filled('mpesa_security_credential')) {
                $init['mpesa_security_credential'] = \Illuminate\Support\Facades\Crypt::encryptString(trim($request->mpesa_security_credential));
            }
            $business->forceFill($init)->save();
        }

        // Whether cashiers/staff may confirm an M-Pesa code the system has no
        // record of. Written straight to the column (when it exists) so this
        // works on databases that haven't run the migration yet.
        if (\Illuminate\Support\Facades\Schema::hasColumn('businesses', 'allow_unverified_mpesa_codes')) {
            $business->forceFill(['allow_unverified_mpesa_codes' => $request->boolean('allow_unverified_mpesa_codes')])->save();
        }

        return redirect()
            ->route('settings.mpesa')
            ->with('success', 'M-Pesa settings saved successfully.');
    }

    // Clears all 4 credential fields at once. Previously there was no way
    // to do this at all — the update form only ever overwrites a sensitive
    // field when a new value is typed in (blank = "keep existing"), so
    // there was no way to actually remove stale/test credentials once
    // saved. That silently left hasMpesaConfigured() returning true (and
    // M-Pesa/QR payment options visible to customers) for a business the
    // owner genuinely believed had nothing configured.
    public function disconnectMpesa() {
        $this->ownerOnly();
        $this->getBusiness()->update([
            'mpesa_shortcode'       => null,
            'mpesa_consumer_key'    => null,
            'mpesa_consumer_secret' => null,
            'mpesa_passkey'         => null,
            'mpesa_till_number'     => null,
            'mpesa_c2b_registered'  => false,
        ]);

        return redirect()
            ->route('settings.mpesa')
            ->with('success', 'M-Pesa disconnected. Customers will no longer see M-Pesa or QR payment options until you reconnect it.');
    }

    // ── Pesapal ────────────────────────────────
    public function pesapal() {
        $this->ownerOnly();
        $business = $this->getBusiness();
        return view('settings.pesapal', compact('business'));
    }

    public function updatePesapal(Request $request) {
        $this->ownerOnly();

        $request->validate([
            'pesapal_consumer_key'    => 'nullable|string|max:255',
            'pesapal_consumer_secret' => 'nullable|string|max:255',
            'pesapal_environment'     => 'required|in:sandbox,production',
        ]);

        $business = $this->getBusiness();
        $data     = ['pesapal_environment' => $request->pesapal_environment];

        if ($request->filled('pesapal_consumer_key')) {
            $data['pesapal_consumer_key'] = $request->pesapal_consumer_key;
        }
        if ($request->filled('pesapal_consumer_secret')) {
            $data['pesapal_consumer_secret'] = $request->pesapal_consumer_secret;
        }

        $business->update($data);

        return redirect()->route('settings.pesapal')
            ->with('success', 'Pesapal credentials saved. Register the IPN to enable auto-confirmation.');
    }

    // ── API Token ──────────────────────────────
    public function api() {
        $this->ownerOnly();
        $business = $this->getBusiness();
        return view('settings.api', compact('business'));
    }

    public function generateApiToken() {
        $this->ownerOnly();
        $business = $this->getBusiness();
        // 'api_access' is org-level only (config/plans.php 'org' => [...]),
        // not a store-level plan key — $business->hasFeature() alone was
        // always false here, so no business could ever generate a token
        // regardless of plan. See the same fix in ApiTokenAuth middleware.
        if (!($business->organization?->hasFeature('api_access') ?? $business->hasFeature('api_access'))) {
            return back()->with('error', 'API access requires the Enterprise plan.');
        }
        $plainToken = \Illuminate\Support\Str::random(60);
        $business->update(['api_token' => hash('sha256', $plainToken)]);
        return back()->with('api_token_plain', $plainToken)
            ->with('success', 'New API token generated. Copy it now — it will not be shown again.');
    }

    public function revokeApiToken() {
        $this->ownerOnly();
        $this->getBusiness()->update(['api_token' => null]);
        return back()->with('success', 'API token revoked.');
    }

    // ── Manager Dashboard (read-only, no login) ────
    // Separate token from api_token on purpose: that one can create/update
    // products and customers, which is more power than "let someone glance
    // at today's sales" needs. This token can only ever open one read-only
    // page — see ManagerViewController — never modify anything, and isn't
    // gated behind any plan, unlike the REST API.
    public function dashboardLink() {
        $this->ownerOnly();
        $business = $this->getBusiness();
        return view('settings.dashboard-link', compact('business'));
    }

    public function generateDashboardToken() {
        $this->ownerOnly();
        $business = $this->getBusiness();
        $plainToken = \Illuminate\Support\Str::random(60);
        $business->update(['dashboard_token' => hash('sha256', $plainToken)]);
        return back()->with('dashboard_token_plain', $plainToken)
            ->with('success', 'New dashboard link generated. Copy it now — it will not be shown again.');
    }

    public function revokeDashboardToken() {
        $this->ownerOnly();
        $this->getBusiness()->update(['dashboard_token' => null]);
        return back()->with('success', 'Dashboard link revoked.');
    }

    // ── Payroll Setup ──────────────────────────
    public function payroll() {
        $this->canManageTeam();
        $business = $this->getBusiness();
        return view('settings.payroll', compact('business'));
    }

    public function updatePayroll(Request $request) {
        $this->canManageTeam();

        $request->validate([
            'enabled'           => 'nullable|boolean',
            'pay_cycle'         => 'required|in:weekly,bi_weekly,monthly',
            'employer_pin'      => 'nullable|string|max:20',
            'deduct_paye'       => 'nullable|boolean',
            'deduct_nssf'       => 'nullable|boolean',
            'deduct_shif'       => 'nullable|boolean',
            'auto_payroll'      => 'nullable|boolean',
            'auto_payroll_mode' => 'nullable|in:calculate_only,auto_approve,fully_auto',
            'pay_day'           => 'nullable|integer|min:1|max:28',
        ]);

        $business = $this->getBusiness();

        $business->update([
            'payroll_settings' => [
                'enabled'           => $request->boolean('enabled'),
                'pay_cycle'         => $request->pay_cycle,
                'employer_pin'      => $request->employer_pin,
                'deductions'        => [
                    'paye' => $request->boolean('deduct_paye'),
                    'nssf' => $request->boolean('deduct_nssf'),
                    'shif' => $request->boolean('deduct_shif'),
                    'housing_levy' => $request->boolean('deduct_housing_levy'),
                ],
                'auto_payroll'      => $request->boolean('auto_payroll'),
                'auto_payroll_mode' => $request->input('auto_payroll_mode', 'calculate_only'),
                'pay_day'           => (int) $request->input('pay_day', 28),
            ],
        ]);

        return back()->with('success', 'Payroll settings saved.');
    }

    // ── eTIMS Settings ─────────────────────────
    public function etims() {
        $this->ownerOnly();
        $business = $this->getBusiness();
        return view('settings.etims', compact('business'));
    }

    public function updateEtims(Request $request) {
        $this->ownerOnly();
        $request->validate([
            'etims_enabled'       => 'nullable|boolean',
            'etims_device_serial' => 'nullable|string|max:50',
            'etims_api_key'       => 'nullable|string|max:255',
            'etims_environment'   => 'required|in:sandbox,production',
            'etims_bhf_id'        => 'nullable|string|size:2',
            'etims_default_item_cls_cd' => 'nullable|string|max:10',
        ]);
        $business = $this->getBusiness();
        $data = [
            'etims_enabled'       => $request->boolean('etims_enabled'),
            'etims_device_serial' => $request->etims_device_serial,
            'etims_environment'   => $request->etims_environment,
            'etims_bhf_id'        => $request->etims_bhf_id ?: '00',
            'etims_default_item_cls_cd' => $request->etims_default_item_cls_cd,
        ];
        // The form shows "(saved - enter new to update)" and never echoes the
        // key back, so a blank field means "keep it". Saving the page for any
        // other reason used to erase the key and silently stop all eTIMS
        // submissions.
        if ($request->filled('etims_api_key')) {
            $data['etims_api_key'] = $request->etims_api_key;
        }
        $business->update($data);
        return back()->with('success', 'eTIMS settings saved.');
    }

    // Activate the OSCU device with KRA and store the communication key.
    public function activateEtims(Request $request) {
        $this->ownerOnly();
        $business = $this->getBusiness();
        $result = \App\Services\EtimsService::forBusiness($business)->initialize();
        return back()->with($result['status'] === 'ok' ? 'success' : 'error', $result['message']);
    }

    // ── VAT Settings ───────────────────────────
    public function vat() {
        $this->ownerOnly();
        $business = $this->getBusiness();
        return view('settings.vat', compact('business'));
    }

    public function updateVat(Request $request) {
        $this->ownerOnly();

        $request->validate([
            'vat_registered' => 'nullable|boolean',
            'vat_number'     => 'nullable|string|max:30',
            'vat_rate'       => 'nullable|numeric|min:0|max:100',
        ]);

        $this->getBusiness()->update([
            'vat_registered' => $request->boolean('vat_registered'),
            'vat_number'     => $request->vat_number,
            'vat_rate'       => $request->vat_rate ?? 16,
        ]);

        return back()->with('success', 'VAT settings saved.');
    }

    // ── Digital Float (cash accountability) ────
    public function cashFloat() {
        $this->ownerOnly();
        $business = $this->getBusiness();
        return view('settings.cash-float', compact('business'));
    }

    public function updateCashFloat(Request $request) {
        $this->ownerOnly();

        $request->validate([
            'enable_digital_float' => 'nullable|boolean',
            'default_credit_limit' => 'nullable|numeric|min:0',
        ]);

        $this->getBusiness()->update([
            'enable_digital_float' => $request->boolean('enable_digital_float'),
            'default_credit_limit' => $request->default_credit_limit ?? 0,
        ]);

        return back()->with('success', 'Digital Float settings saved.');
    }

    // ── WhatsApp Settings ──────────────────────
    public function whatsapp() {
        $this->ownerOnly();
        $business = $this->getBusiness();
        return view('settings.whatsapp', compact('business'));
    }

    public function updateWhatsapp(Request $request) {
        $this->ownerOnly();
        $this->getBusiness()->update([
            'whatsapp_enabled' => $request->boolean('whatsapp_enabled'),
        ]);
        return back()->with('success', 'WhatsApp settings saved.');
    }

    public function testWhatsapp(Request $request) {
        $this->ownerOnly();
        $request->validate(['phone' => 'required|string']);
        $business = $this->getBusiness();
        $wa = WhatsAppService::forBusiness($business);
        if (!$wa->isConfigured()) {
            return back()->with('error', 'WhatsApp is not configured. Enable it and ensure SMS credentials are set.');
        }
        $sent = $wa->send($request->phone, "Test WhatsApp message from {$business->name}. Your integration is working!");

        // Was "Failed to send test message. Check logs." — completely
        // useless to a shop owner on shared hosting with no log access.
        // getLastError() now carries the actual reason (the provider's own
        // error message when the API call itself failed, e.g. an unapproved
        // WhatsApp sender or invalid number — the exact gap isConfigured()
        // can't check for since that's a separate approval step on the
        // provider's own dashboard, not anything visible from here).
        $failMsg = 'Failed to send test message: ' . ($wa->getLastError() ?? 'unknown error.');

        return back()->with($sent ? 'success' : 'error', $sent ? 'Test message sent.' : $failMsg);
    }

    // ── Loyalty Settings ───────────────────────
    public function loyalty() {
        $this->ownerOnly();
        $business = $this->getBusiness();
        $program  = LoyaltyProgram::where('business_id', $business->id)->first();
        return view('settings.loyalty', compact('business', 'program'));
    }

    public function updateLoyalty(Request $request) {
        $this->ownerOnly();
        $request->validate([
            'name'                  => 'required|string|max:100',
            'points_per_shilling'   => 'required|numeric|min:0',
            'redemption_rate'       => 'required|numeric|min:0',
            'min_redemption_points' => 'required|integer|min:1',
            'is_active'             => 'nullable|boolean',
        ]);

        LoyaltyProgram::updateOrCreate(
            ['business_id' => $this->businessId()],
            [
                'name'                  => $request->name,
                'points_per_shilling'   => $request->points_per_shilling,
                'redemption_rate'       => $request->redemption_rate,
                'min_redemption_points' => $request->min_redemption_points,
                'is_active'             => $request->boolean('is_active'),
            ]
        );

        return back()->with('success', 'Loyalty program settings saved.');
    }

    // ── Helpers ────────────────────────────────

    // Owners/overall managers have full team access. Branch managers may only
    // manage their own store's members (enforced in each method below).
    private function ownerOnly(): void {
        if (!Auth::user()->canActAsOwner()) {
            abort(403, 'Only the business owner can manage team settings.');
        }
    }

    private function canManageTeam(): void {
        $user = Auth::user();
        if (!$user->canActAsOwner() && !$user->hasRole('manager')) {
            abort(403, 'You do not have permission to manage team members.');
        }
    }

    // Only owners/overall managers may appoint an overall manager.
    // Branch managers are limited to cashier/staff/manager roles.
    private function assertCanAssignRole(string $role): void {
        $user = Auth::user();
        if ($role === 'overall_manager' && !$user->isOwner()) {
            abort(403, 'Only the organization owner can appoint an overall manager.');
        }
        // Only the owner may create or promote another owner — this used to
        // block just plain managers, so an overall manager could mint an owner.
        if ($role === 'owner' && !$user->isOwner()) {
            abort(403, 'Only the owner can assign the owner role.');
        }
    }

    private function authorizeMember(User $user): void {
        if (!$user->businesses()->where('business_id', $this->businessId())->exists()) {
            abort(403, 'Unauthorized action.');
        }
        if ($user->isOwner()) {
            abort(403, 'Cannot modify owner account.');
        }
        // Nobody below the owner may modify an overall manager — a plain
        // manager could otherwise disable, demote or delete someone senior.
        if ($user->isOverallManager() && !Auth::user()->isOwner()) {
            abort(403, 'Only the owner can modify an overall manager.');
        }
    }

    // ── Delivery Settings ──────────────────────
    public function deliverySettings() {
        $business = \Illuminate\Support\Facades\Auth::user()->currentBusiness();
        $zones = json_decode($business->delivery_zones ?? '[]', true);
        return view('settings.delivery', compact('business', 'zones'));
    }

    public function updateDeliverySettings(\Illuminate\Http\Request $request) {
        $business = \Illuminate\Support\Facades\Auth::user()->currentBusiness();
        $request->validate([
            'delivery_fee' => 'nullable|numeric|min:0',
            'delivery_zones' => 'nullable|string',
        ]);
        $zones = [];
        if ($request->zone_name) {
            foreach ($request->zone_name as $i => $name) {
                if ($name) $zones[] = ['name' => $name, 'fee' => $request->zone_fee[$i] ?? 0];
            }
        }
        $business->update([
            'delivery_fee' => $request->delivery_fee ?? 0,
            'delivery_zones' => json_encode($zones),
        ]);
        return back()->with('success', 'Delivery settings updated.');
    }

    public function store() {
        $business = Auth::user()->currentBusiness();

        // The QR code for this store's online shop (only once it has a web address).
        $shopUrl = \App\Support\ShopQr::shopUrl($business);
        $qrSvg   = $shopUrl ? \App\Support\ShopQr::svg(\App\Support\ShopQr::shopUrl($business, true), 320) : null;

        return view('settings.store', compact('business', 'shopUrl', 'qrSvg'));
    }

    // The QR as a file: ?download=1 saves it, otherwise it displays.
    public function storeQr(Request $request) {
        $business = Auth::user()->currentBusiness();
        abort_unless($business->store_slug, 404);

        $svg = \App\Support\ShopQr::svg(\App\Support\ShopQr::shopUrl($business, true), 1024);
        $headers = ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'no-store'];
        if ($request->boolean('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="shop-qr-' . $business->store_slug . '.svg"';
        }

        return response('<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $svg, 200, $headers);
    }

    // A print-ready poster / sign / sticker with the QR code.
    public function storePoster(Request $request) {
        $business = Auth::user()->currentBusiness();
        abort_unless($business->store_slug, 404);

        $size    = in_array($request->query('size'), ['a4', 'a5', 'sticker'], true) ? $request->query('size') : 'a4';
        $shopUrl = \App\Support\ShopQr::shopUrl($business);
        $qrSvg   = \App\Support\ShopQr::svg(\App\Support\ShopQr::shopUrl($business, true), 600);

        return view('settings.store-poster', compact('business', 'shopUrl', 'qrSvg', 'size'));
    }

    // ── Branding / Receipt Customisation ──────────────
    public function branding()
    {
        $business = \Illuminate\Support\Facades\Auth::user()->currentBusiness();
        return view('settings.branding', compact('business'));
    }

    public function updateBranding(\Illuminate\Http\Request $request)
    {
        $business = \Illuminate\Support\Facades\Auth::user()->currentBusiness();
        $validated = $request->validate([
            'logo'                 => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            'receipt_header'       => 'nullable|string|max:500',
            'receipt_footer'       => 'nullable|string|max:500',
            'receipt_color'        => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'invoice_terms'        => 'nullable|string|max:1000',
            'invoice_bank_details' => 'nullable|string|max:500',
            'show_logo_on_receipt' => 'nullable|boolean',
            'show_shop_qr_on_receipt' => 'nullable|boolean',
            'show_logo_on_invoice' => 'nullable|boolean',
            'receipt_tagline'      => 'nullable|string|max:200',
        ]);
        $validated['show_logo_on_receipt'] = $request->boolean('show_logo_on_receipt');
        $validated['show_shop_qr_on_receipt'] = $request->boolean('show_shop_qr_on_receipt');
        $validated['show_logo_on_invoice'] = $request->boolean('show_logo_on_invoice');

        if ($request->hasFile('logo')) {
            // Every reference to the logo (invoices, receipts, statements,
            // the shop layout) reads it live off business.logo rather than
            // snapshotting the path per-record, so — unlike product images
            // — nothing else still points at the old file once replaced.
            // Safe to delete it instead of leaving it orphaned in storage.
            if ($business->logo) {
                Storage::disk('public')->delete($business->logo);
            }
            $validated['logo'] = $request->file('logo')->store('logos', 'public');
        } else {
            unset($validated['logo']);
        }

        $business->update($validated);
        return back()->with('success', 'Branding settings updated.');
    }

    public function removeLogo()
    {
        $business = Auth::user()->currentBusiness();

        if ($business->logo) {
            Storage::disk('public')->delete($business->logo);
            $business->update(['logo' => null]);
        }

        return back()->with('success', 'Logo removed.');
    }

    public function updateStore(Request $request) {
        $business = Auth::user()->currentBusiness();
        $request->validate([
            'store_slug'        => ['required', 'string', 'max:100', 'regex:/^[a-z0-9\-]+$/',
                \Illuminate\Validation\Rule::unique('businesses', 'store_slug')->ignore($business->id)],
            'store_description' => 'nullable|string|max:500',
            'store_public'      => 'nullable|boolean',
        ]);

        $business->update([
            'store_slug'        => $request->store_slug,
            'store_description' => $request->store_description,
            'store_public'      => $request->boolean('store_public'),
        ]);

        return back()->with('success', 'Store settings updated.');
    }
}