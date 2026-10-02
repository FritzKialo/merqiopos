<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\Feature\Helpers\CreatesOrganization;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use CreatesOrganization;

    private function fakeGoogleUser(array $attributes = []): void
    {
        Socialite::fake('google', SocialiteUser::fake(array_merge([
            'id'    => 'g-123456',
            'name'  => 'Jane Owner',
            'email' => 'jane.owner@example.com',
        ], $attributes)));
    }

    /** @test */
    public function a_brand_new_google_user_is_created_and_sent_to_onboarding(): void
    {
        $this->fakeGoogleUser();

        $response = $this->get(route('google.callback'));

        $user = User::where('email', 'jane.owner@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('g-123456', $user->google_id);
        $this->assertNull($user->password);
        $this->assertSame('owner', $user->role);
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('onboarding.business'));
    }

    /** @test */
    public function completing_onboarding_creates_the_organization_and_business(): void
    {
        $this->fakeGoogleUser();
        $this->get(route('google.callback'));

        $user = User::where('email', 'jane.owner@example.com')->first();

        $response = $this->actingAs($user)->post(route('onboarding.business.store'), [
            'business_name'  => 'Jane Stores',
            'business_email' => 'contact@janestores.example.com',
            'business_phone' => '0712345678',
        ]);

        $user->refresh();

        $response->assertRedirect(route('org.dashboard'));
        $this->assertNotNull($user->organization_id);
        $this->assertDatabaseHas('businesses', ['name' => 'Jane Stores']);
        $this->assertDatabaseHas('business_user', ['user_id' => $user->id, 'role' => 'owner']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'event' => 'organization.registered']);
    }

    /** @test */
    public function a_returning_google_user_is_recognized_by_google_id_and_logged_in(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();
        $owner->update(['google_id' => 'g-123456']);

        $this->fakeGoogleUser(['email' => $owner->email]);

        $response = $this->get(route('google.callback'));

        $this->assertAuthenticatedAs($owner);
        $response->assertRedirect(route('menu'));
    }

    /** @test */
    public function an_existing_password_account_is_linked_by_email_instead_of_duplicated(): void
    {
        [$owner] = $this->scaffoldOrg();
        $this->assertNull($owner->google_id);

        $this->fakeGoogleUser(['email' => $owner->email]);

        $this->get(route('google.callback'));

        $owner->refresh();
        $this->assertSame('g-123456', $owner->google_id);
        $this->assertSame(1, User::where('email', $owner->email)->count());
    }

    /** @test */
    public function a_suspended_organizations_owner_is_blocked_the_same_way_as_password_login(): void
    {
        [$owner, $organization] = $this->scaffoldOrg();
        $organization->update(['status' => 'suspended']);
        $owner->update(['google_id' => 'g-123456']);

        $this->fakeGoogleUser(['email' => $owner->email]);

        $response = $this->get(route('google.callback'));

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
    }
}
