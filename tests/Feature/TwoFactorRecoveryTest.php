<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\Feature\Helpers\CreatesOrganization;
use Tests\TestCase;

class TwoFactorRecoveryTest extends TestCase
{
    use CreatesOrganization;

    private function enableTwoFactorWithRecoveryCodes($user, array $plainCodes = ['AAAAA-11111', 'BBBBB-22222']): array
    {
        $secret = (new Google2FA())->generateSecretKey();
        $user->forceFill([
            'google2fa_secret'          => $secret,
            'two_factor_enabled'        => true,
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => array_map(fn ($c) => Hash::make($c), $plainCodes),
        ])->save();

        return [$secret, $plainCodes];
    }

    /** @test */
    public function enabling_two_factor_generates_eight_recovery_codes_shown_once(): void
    {
        [$owner] = $this->scaffoldOrg();
        $secret = (new Google2FA())->generateSecretKey();
        session(['2fa_setup_secret' => $secret]);

        $code = (new Google2FA())->getCurrentOtp($secret);

        $response = $this->actingAs($owner)->post(route('settings.2fa.confirm'), ['code' => $code]);

        $owner->refresh();
        $this->assertTrue($owner->two_factor_enabled);
        $this->assertCount(8, $owner->two_factor_recovery_codes);
        $response->assertSessionHas('2fa_fresh_recovery_codes');
        $this->assertCount(8, session('2fa_fresh_recovery_codes'));
    }

    /** @test */
    public function a_recovery_code_logs_the_user_in_and_is_then_consumed(): void
    {
        [$owner] = $this->scaffoldOrg();
        [, $plainCodes] = $this->enableTwoFactorWithRecoveryCodes($owner);

        $this->actingAs($owner);
        session(['2fa_intended' => route('menu')]);

        $response = $this->post(route('2fa.verify'), ['code' => $plainCodes[0]]);

        $response->assertRedirect(route('menu'));
        $this->assertTrue(session('2fa_verified'));

        $owner->refresh();
        $this->assertCount(1, $owner->two_factor_recovery_codes);
    }

    /** @test */
    public function a_used_recovery_code_cannot_be_reused(): void
    {
        [$owner] = $this->scaffoldOrg();
        [, $plainCodes] = $this->enableTwoFactorWithRecoveryCodes($owner);

        $this->actingAs($owner);
        $this->post(route('2fa.verify'), ['code' => $plainCodes[0]]);
        session()->forget('2fa_verified');

        $response = $this->post(route('2fa.verify'), ['code' => $plainCodes[0]]);

        $response->assertSessionHas('error');
        $this->assertNull(session('2fa_verified'));
    }

    /** @test */
    public function regenerating_recovery_codes_invalidates_the_old_set(): void
    {
        [$owner] = $this->scaffoldOrg();
        [$secret, $plainCodes] = $this->enableTwoFactorWithRecoveryCodes($owner);

        $currentOtp = (new Google2FA())->getCurrentOtp($secret);

        $response = $this->actingAs($owner)
            ->withSession(['2fa_verified' => true, '2fa_last_verified' => now()->timestamp])
            ->post(route('settings.2fa.recovery-codes.regenerate'), [
                'password' => 'password',
                'code'     => $currentOtp,
            ]);

        $response->assertSessionHas('2fa_fresh_recovery_codes');
        $newCodes = session('2fa_fresh_recovery_codes');
        $this->assertCount(8, $newCodes);
        $this->assertNotEquals($plainCodes, array_slice($newCodes, 0, 2));

        session()->forget('2fa_verified');
        $this->actingAs($owner->fresh());
        $attemptWithOldCode = $this->post(route('2fa.verify'), ['code' => $plainCodes[0]]);
        $attemptWithOldCode->assertSessionHas('error');
        $this->assertNull(session('2fa_verified'));
    }

    /** @test */
    public function an_owner_can_reset_a_team_members_two_factor(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $staff = User::factory()->cashier()->create();
        $business->users()->attach($staff->id, ['role' => 'cashier']);
        $this->enableTwoFactorWithRecoveryCodes($staff);

        $response = $this->actingAs($owner)->post(route('settings.team.reset-2fa', $staff));

        $response->assertRedirect(route('settings.team'));
        $staff->refresh();
        $this->assertFalse($staff->two_factor_enabled);
        $this->assertNull($staff->google2fa_secret);
        $this->assertNull($staff->two_factor_recovery_codes);
    }

    /** @test */
    public function admin_can_reset_an_organizations_owner_two_factor(): void
    {
        [$owner, $organization] = $this->scaffoldOrg();
        $this->enableTwoFactorWithRecoveryCodes($owner);

        $admin = User::factory()->create(['is_super_admin' => true]);
        $this->enableTwoFactorWithRecoveryCodes($admin, ['CCCCC-33333']);

        $response = $this->actingAs($admin)
            ->withSession(['2fa_verified' => true, '2fa_last_verified' => now()->timestamp])
            ->post(route('admin.organizations.reset-2fa', $organization));

        $response->assertRedirect();
        $owner->refresh();
        $this->assertFalse($owner->two_factor_enabled);
        $this->assertNull($owner->two_factor_recovery_codes);
    }
}
