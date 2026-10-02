<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessOnboardingRequest;
use App\Mail\WelcomeEmail;
use App\Services\BusinessAccountProvisioner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

/**
 * The one extra step a brand-new Google sign-up needs that a password
 * registration doesn't: Google only hands us a name and email, never a
 * business name/phone/etc., so the account exists (created in
 * GoogleAuthController) before we know anything about their business.
 * This form collects that and hands off to the same
 * BusinessAccountProvisioner that password registration uses, so both
 * paths end up with an identical organization/business shape.
 */
class OnboardingController extends Controller
{
    public function __construct(private BusinessAccountProvisioner $provisioner)
    {
    }

    public function showBusinessForm()
    {
        $user = Auth::user();

        if ($user->organization_id || $user->businesses()->exists()) {
            return redirect()->route('org.dashboard');
        }

        return view('auth.onboarding-business', ['user' => $user]);
    }

    public function storeBusiness(BusinessOnboardingRequest $request)
    {
        $user = Auth::user();

        if ($user->organization_id || $user->businesses()->exists()) {
            return redirect()->route('org.dashboard');
        }

        try {
            $business = $this->provisioner->provision($user, $request->validated(), $request->ip());

            try {
                Mail::to($user->email)->queue(new WelcomeEmail($user, $business));
            } catch (\Exception) {}

            return redirect()
                ->route('org.dashboard')
                ->with('success', 'Welcome! Your 1-month free trial has started.');

        } catch (\Exception) {
            return back()
                ->withInput()
                ->with('error', 'Something went wrong setting up your business. Please try again.');
        }
    }
}
