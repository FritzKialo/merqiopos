<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;
use Tests\Feature\Helpers\CreatesOrganization;
use Tests\TestCase;

/**
 * Regression coverage for two related production bugs, both on the sudo
 * (RequireRecentTwoFactor) path:
 *
 * 1. verify() used back() for the sudo branch — back() resolves to the
 *    challenge page itself (the last full-page GET), so confirming
 *    identity just looped back to asking again, forever.
 *
 * 2. Fixing #1 naively (redirect to the literal $request->url() that
 *    triggered the challenge) broke every REAL sudo-gated route in this
 *    app, because every one of them — grant-subscription, business.update,
 *    team.store, pesapal.update, etc. — is a mutating POST/PUT/PATCH
 *    action, never a GET page. redirect()ing back to a POST-only URL
 *    issues a GET, which 405s. Confirmed live: admin clicked "Grant
 *    Subscription", 2FA-challenged, confirmed the code, landed on
 *    /admin/organizations/2/grant-subscription via GET -> 405.
 */
class TwoFactorSudoTest extends TestCase
{
    use CreatesOrganization;

    private function enableTwoFactor($user): string
    {
        $secret = (new Google2FA())->generateSecretKey();
        $user->forceFill([
            'google2fa_secret' => $secret, 'two_factor_enabled' => true, 'two_factor_confirmed_at' => now(),
        ])->save();

        return $secret;
    }

    /** @test */
    public function confirming_identity_after_a_sudo_gated_action_sends_you_back_to_a_retryable_page_not_the_action_url(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $secret = $this->enableTwoFactor($owner);

        // Realistic flow: the admin is looking at the Pesapal settings page
        // (a GET — this is what establishes Laravel's "previous URL")...
        $settingsPage = route('settings.pesapal');
        $this->actingAs($owner)
            ->withSession(['2fa_verified' => true, '2fa_last_verified' => now()->timestamp])
            ->get($settingsPage)
            ->assertOk();

        // ...then submits the form (a PUT, sudo-gated) after the 15-minute
        // window has lapsed.
        $actionUrl = route('settings.pesapal.update');
        $this->actingAs($owner)
            ->withSession(['2fa_verified' => true, '2fa_last_verified' => now()->subMinutes(30)->timestamp])
            ->put($actionUrl, ['pesapal_environment' => 'sandbox'])
            ->assertRedirect(route('2fa.challenge', ['intent' => 'sudo']));

        // The remembered "intended" URL must be the GET page the form lives
        // on. (Note: settings.pesapal and settings.pesapal.update happen to
        // share the same URI, differing only by verb — so $actionUrl and
        // $settingsPage are textually identical here; the genuinely
        // different-URI case is covered by the grant-subscription test
        // below, which is what actually exposed this bug live.)
        $this->assertEquals($settingsPage, session('2fa_intended'));

        $g = new Google2FA();
        $code = $g->getCurrentOtp($secret);

        $response = $this->post(route('2fa.verify'), ['code' => $code, 'intent' => 'sudo']);

        $response->assertRedirect($settingsPage);
        $response->assertSessionHas('success', 'Identity confirmed.');
        $this->assertNull(session('2fa_intended'), '2fa_intended should be consumed, not left behind for the next request.');

        // The critical assertion: actually following that redirect must
        // succeed, not 405 — this is what a real browser does next.
        $this->followingRedirects()->get($settingsPage)->assertOk();

        Cache::forget('2fa-used:' . $owner->id . ':' . $code);
    }

    /** @test */
    public function the_exact_reported_bug_admin_grant_subscription_no_longer_405s_after_confirming(): void
    {
        [, $org] = $this->scaffoldOrg();
        $admin  = \App\Models\User::factory()->superAdmin()->create();
        $secret = $this->enableTwoFactor($admin);

        $orgShowPage = route('admin.organizations.show', $org);
        $this->actingAs($admin)
            ->withSession(['2fa_verified' => true, '2fa_last_verified' => now()->timestamp])
            ->get($orgShowPage)
            ->assertOk();

        $grantUrl = route('admin.organizations.grant-subscription', $org);
        $this->actingAs($admin)
            ->withSession(['2fa_verified' => true, '2fa_last_verified' => now()->subMinutes(30)->timestamp])
            ->post($grantUrl, ['plan' => 'growth', 'months' => 1])
            ->assertRedirect(route('2fa.challenge', ['intent' => 'sudo']));

        $this->assertEquals($orgShowPage, session('2fa_intended'));

        $g = new Google2FA();
        $code = $g->getCurrentOtp($secret);

        $this->post(route('2fa.verify'), ['code' => $code, 'intent' => 'sudo'])
            ->assertRedirect($orgShowPage);

        // Following that redirect is exactly what the admin's browser did
        // live — it must land on the org page (200), not 405 the grant URL.
        $this->followingRedirects()->get($orgShowPage)->assertOk();

        Cache::forget('2fa-used:' . $admin->id . ':' . $code);
    }

    /** @test */
    public function login_intent_still_redirects_to_the_originally_intended_page(): void
    {
        [$owner] = $this->scaffoldOrg();
        $secret = $this->enableTwoFactor($owner);

        session(['2fa_intended' => route('sales.index')]);

        $g = new Google2FA();
        $code = $g->getCurrentOtp($secret);

        $response = $this->actingAs($owner)->post(route('2fa.verify'), ['code' => $code, 'intent' => 'login']);

        $response->assertRedirect(route('sales.index'));

        Cache::forget('2fa-used:' . $owner->id . ':' . $code);
    }
}
