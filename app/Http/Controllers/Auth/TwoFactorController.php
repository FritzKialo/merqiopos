<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    private const RECOVERY_CODE_COUNT = 8;

    /**
     * A valid authenticator code stays valid for about a minute. Anyone who saw
     * or intercepted one (shoulder-surfing, a phishing page that relays it,
     * a proxy log) could replay it within that window. Each code is therefore
     * accepted once per user: Cache::add() is atomic, so of two simultaneous
     * uses only one wins. Only a code that already checked out as correct is
     * recorded, so a typo never burns a code.
     */
    private function consumeCode($user, string $code): bool
    {
        return Cache::add('2fa-used:' . $user->id . ':' . $code, 1, now()->addSeconds(150));
    }

    private const REPLAY_MESSAGE = 'That code was already used. Wait for your authenticator app to show the next one (about 30 seconds) and try again.';

    /**
     * Plain-text codes, grouped for readability (e.g. "A1B2C-D3E4F").
     * Only ever returned here and flashed to session once — from this
     * point on only the hashed form (see hashRecoveryCodes) is kept.
     */
    private function generatePlainRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn () => strtoupper(Str::random(5) . '-' . Str::random(5)))
            ->all();
    }

    private function hashRecoveryCodes(array $plainCodes): array
    {
        return array_map(fn ($code) => Hash::make($code), $plainCodes);
    }

    /**
     * A recovery code is a single-use substitute for the TOTP code — once
     * matched it's removed from the stored set immediately, so the same
     * code (even if written down and reused by mistake, or intercepted)
     * can't grant a second login.
     */
    private function verifyAndConsumeRecoveryCode($user, string $submitted): bool
    {
        $hashedCodes = $user->two_factor_recovery_codes ?? [];
        $normalized  = strtoupper(trim($submitted));

        foreach ($hashedCodes as $index => $hashedCode) {
            if (Hash::check($normalized, $hashedCode)) {
                unset($hashedCodes[$index]);
                $user->update(['two_factor_recovery_codes' => array_values($hashedCodes)]);
                return true;
            }
        }

        return false;
    }

    // ── Setup page (GET /settings/2fa) ────────────────────────────────────────
    public function setup()
    {
        $user = Auth::user();

        $data = [
            'enabled' => false,
            'qrCodeUrl' => null,
            'secret' => null,
            'recoveryCodes' => session('2fa_fresh_recovery_codes'),
            'recoveryCodesRemaining' => count($user->two_factor_recovery_codes ?? []),
        ];

        if ($user->hasTwoFactorEnabled()) {
            $data['enabled'] = true;
        } else {
            $google2fa = new Google2FA();
            $secret    = $google2fa->generateSecretKey();
            session(['2fa_setup_secret' => $secret]);
            $data['qrCodeUrl'] = $google2fa->getQRCodeUrl(
                config('app.name'), $user->email, $secret
            );
            $data['secret'] = $secret;
        }

        // Shown once, right after the page that generated them — never again.
        session()->forget('2fa_fresh_recovery_codes');

        // Super admin has no business — use the admin layout wrapper
        if ($user->isSuperAdmin()) {
            return view('settings.two-factor-admin', $data);
        }

        return view('settings.two-factor', $data);
    }

    // ── Confirm setup (POST /settings/2fa/confirm) ────────────────────────────
    public function confirm(Request $request)
    {
        $request->validate([
            'code' => 'required|string|digits:6',
        ]);

        $user   = Auth::user();
        $secret = session('2fa_setup_secret');

        if (! $secret) {
            return back()->with('error', 'Setup session expired. Please start again.');
        }

        $google2fa = new Google2FA();
        $valid     = $google2fa->verifyKey($secret, $request->code);

        if (! $valid) {
            return back()->with('error', 'Invalid code. Please try again.');
        }

        if (! $this->consumeCode($user, $request->code)) {
            return back()->with('error', self::REPLAY_MESSAGE);
        }

        $plainRecoveryCodes = $this->generatePlainRecoveryCodes();

        $user->update([
            'google2fa_secret'           => $secret,
            'two_factor_enabled'         => true,
            'two_factor_confirmed_at'    => now(),
            'two_factor_recovery_codes'  => $this->hashRecoveryCodes($plainRecoveryCodes),
        ]);

        session()->forget('2fa_setup_secret');
        session(['2fa_verified' => true, '2fa_last_verified' => now()->timestamp]);
        session()->flash('2fa_fresh_recovery_codes', $plainRecoveryCodes);

        AuditLog::record('2fa.enabled', $user);

        return redirect()
            ->route('settings.2fa.setup')
            ->with('success', 'Two-factor authentication enabled successfully. Save your recovery codes below before you leave this page.');
    }

    // ── Regenerate recovery codes (POST /settings/2fa/recovery-codes/regenerate) ──
    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
            'code'     => 'required|string|digits:6',
        ]);

        $user = Auth::user();

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('settings.2fa.setup');
        }

        if (! Hash::check($request->password, $user->password)) {
            return back()->with('error', 'Incorrect password.');
        }

        $google2fa = new Google2FA();
        if (! $google2fa->verifyKey($user->google2fa_secret, $request->code)) {
            return back()->with('error', 'Invalid authenticator code.');
        }

        if (! $this->consumeCode($user, $request->code)) {
            return back()->with('error', self::REPLAY_MESSAGE);
        }

        $plainRecoveryCodes = $this->generatePlainRecoveryCodes();

        $user->update([
            'two_factor_recovery_codes' => $this->hashRecoveryCodes($plainRecoveryCodes),
        ]);

        session()->flash('2fa_fresh_recovery_codes', $plainRecoveryCodes);

        AuditLog::record('2fa.recovery_codes_regenerated', $user);

        return redirect()
            ->route('settings.2fa.setup')
            ->with('success', 'New recovery codes generated. Your old codes no longer work — save the new ones below.');
    }

    // ── Disable 2FA (POST /settings/2fa/disable) ─────────────────────────────
    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
            'code'     => 'required|string|digits:6',
        ]);

        $user = Auth::user();

        if (! Hash::check($request->password, $user->password)) {
            return back()->with('error', 'Incorrect password.');
        }

        $google2fa = new Google2FA();
        $valid     = $google2fa->verifyKey($user->google2fa_secret, $request->code);

        if (! $valid) {
            return back()->with('error', 'Invalid authenticator code.');
        }

        if (! $this->consumeCode($user, $request->code)) {
            return back()->with('error', self::REPLAY_MESSAGE);
        }

        $user->update([
            'google2fa_secret'          => null,
            'two_factor_enabled'        => false,
            'two_factor_confirmed_at'   => null,
            'two_factor_recovery_codes' => null,
        ]);

        session()->forget(['2fa_verified', '2fa_last_verified']);

        AuditLog::record('2fa.disabled', $user);

        return redirect()
            ->route('settings.2fa.setup')
            ->with('success', 'Two-factor authentication has been disabled.');
    }

    // ── Challenge page (GET /2fa/challenge) ───────────────────────────────────
    public function challenge(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        // If already verified this session, skip challenge
        if (session('2fa_verified') && $request->input('intent') !== 'sudo') {
            return redirect()->intended(Auth::user()->postLoginRoute());
        }

        return view('auth.two-factor-challenge', [
            'intent' => $request->input('intent', 'login'),
        ]);
    }

    // ── Verify OTP or recovery code (POST /2fa/verify) ────────────────────────
    public function verify(Request $request)
    {
        $request->validate([
            'code'   => 'required|string',
            'intent' => 'nullable|string|in:login,sudo',
        ]);

        $user = Auth::user();

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            return redirect()->intended($user ? $user->postLoginRoute() : route('dashboard'));
        }

        $submitted = trim($request->code);
        $usedRecoveryCode = false;

        // A 6-digit TOTP code and a recovery code ("XXXXX-XXXXX") never
        // overlap in shape, so the same field can accept either without
        // the user needing to pick a mode first.
        if (preg_match('/^\d{6}$/', $submitted)) {
            $google2fa = new Google2FA();

            if (! $google2fa->verifyKey($user->google2fa_secret, $submitted)) {
                return back()->withInput($request->only('intent'))->with('error', 'Invalid code. Please try again.');
            }

            if (! $this->consumeCode($user, $submitted)) {
                return back()->withInput($request->only('intent'))->with('error', self::REPLAY_MESSAGE);
            }
        } else {
            if (! $this->verifyAndConsumeRecoveryCode($user, $submitted)) {
                return back()->withInput($request->only('intent'))->with('error', 'That recovery code is invalid or has already been used.');
            }

            AuditLog::record('2fa.recovery_code_used', $user);
            $usedRecoveryCode = true;
        }

        session(['2fa_verified' => true, '2fa_last_verified' => now()->timestamp]);

        $intended = session()->pull('2fa_intended', $user->postLoginRoute());

        if ($request->input('intent') === 'sudo') {
            return redirect($intended)->with('success', 'Identity confirmed.');
        }

        if ($usedRecoveryCode) {
            $remaining = count($user->fresh()->two_factor_recovery_codes ?? []);
            return redirect($intended)->with('warning', $remaining > 0
                ? "Signed in with a recovery code. {$remaining} recovery " . ($remaining === 1 ? 'code remains' : 'codes remain') . " — generate new ones from Settings → Two-Factor if you're running low."
                : "Signed in with your last recovery code. Generate new ones from Settings → Two-Factor now so you're not locked out next time.");
        }

        return redirect($intended);
    }
}
