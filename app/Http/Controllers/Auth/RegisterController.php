<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Mail\WelcomeEmail;
use App\Models\User;
use App\Services\BusinessAccountProvisioner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class RegisterController extends Controller
{
    public function __construct(private BusinessAccountProvisioner $provisioner)
    {
    }

    public function showForm()
    {
        if (Auth::check()) {
            return redirect()->route('org.dashboard');
        }
        return view('auth.register');
    }

    public function register(RegisterRequest $request)
    {
        try {
            // User creation and business provisioning both need to succeed
            // together — DB::transaction() nests safely (via a savepoint)
            // inside the provisioner's own transaction, so a failure at
            // either step rolls back the whole thing atomically instead of
            // leaving an orphaned user or a user reported as "failed" while
            // actually already committed.
            [$user, $business] = DB::transaction(function () use ($request) {
                $user = User::create([
                    'name'          => $request->owner_name,
                    'email'         => $request->owner_email,
                    'password'      => Hash::make($request->password),
                    'role'          => 'owner',
                    'last_login_at' => now(),
                ]);

                $business = $this->provisioner->provision($user, $request->only([
                    'business_name', 'business_email', 'business_phone',
                    'business_address', 'business_city', 'industry', 'business_type',
                ]), $request->ip());

                return [$user, $business];
            });

            try {
                Mail::to($user->email)->queue(new WelcomeEmail($user, $business));
            } catch (\Exception) {}

            Auth::login($user);

            return redirect()
                ->route('org.dashboard')
                ->with('success', 'Welcome! Your 1-month free trial has started.');

        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Registration failed. Please try again.');
        }
    }
}
