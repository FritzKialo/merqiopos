<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\HandlesPostAuthentication;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    use HandlesPostAuthentication;

    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception) {
            return redirect()->route('login')
                ->with('error', 'Google sign-in was cancelled or failed. Please try again.');
        }

        $user = User::where('google_id', $googleUser->getId())->first();

        // Not linked yet, but an account with this email already exists
        // (they originally signed up with a password) — link it rather
        // than creating a second, duplicate account for the same person.
        if (! $user) {
            $user = User::where('email', $googleUser->getEmail())->first();
            if ($user) {
                $user->update(['google_id' => $googleUser->getId()]);
            }
        }

        // Brand new sign-up. Google only gives us a name and email — no
        // business info — so create the bare account, sign them in, and
        // send them to finish setting up their organization before they
        // can reach the dashboard.
        if (! $user) {
            $user = User::create([
                'name'          => $googleUser->getName() ?: $googleUser->getEmail(),
                'email'         => $googleUser->getEmail(),
                'password'      => null,
                'google_id'     => $googleUser->getId(),
                'role'          => 'owner',
                'last_login_at' => now(),
            ]);

            Auth::login($user);

            return redirect()->route('onboarding.business');
        }

        $user->update(['last_login_at' => now()]);
        Auth::login($user);

        // An owner who signed up via Google but never finished onboarding
        // (closed the tab, etc.) — send them back to finish it rather than
        // into a dashboard for an organization that doesn't exist yet.
        if (! $user->organization_id && $user->canActAsOwner() && ! $user->businesses()->exists()) {
            return redirect()->route('onboarding.business');
        }

        return $this->postAuthenticationRedirect($user);
    }
}
