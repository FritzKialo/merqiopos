<?php

namespace Tests\Feature\Helpers;

use App\Models\Business;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\User;

trait CreatesOrganization
{
    /**
     * Create an owner user + organization + one business store.
     * Returns [$owner, $organization, $business].
     */
    protected function scaffoldOrg(string $plan = 'growth'): array
    {
        $owner = User::factory()->owner()->create();

        $organization = Organization::factory()->create([
            'owner_user_id'     => $owner->id,
            'subscription_plan' => $plan,
            'status'            => 'active',
        ]);

        $owner->update(['organization_id' => $organization->id]);

        $business = Business::factory()->withPayroll()->create([
            'organization_id' => $organization->id,
        ]);

        // Attach owner to the business pivot
        $business->users()->attach($owner->id, ['role' => 'owner']);

        // status='active' alone doesn't satisfy CheckSubscription — it needs either an
        // Organization on trial (status='trial' + a future trial_ends_at) or a real
        // Subscription row with status='active' and a future end_date. Without one of
        // those, every route behind the 'subscribed' middleware bounces to
        // subscription.required before reaching the controller under test at all —
        // this was silently true of this helper until it surfaced as a wall of
        // "expected 200/403, got 302" failures once an unrelated APP_URL fix let the
        // test's route() calls actually reach real routes for the first time.
        Subscription::create([
            'organization_id' => $organization->id,
            'business_id'     => $business->id,
            'plan'            => 'enterprise',
            'amount'          => 0,
            'start_date'      => now()->toDateString(),
            'end_date'        => now()->addYear()->toDateString(),
            'status'          => 'active',
        ]);

        // Set active store in session
        session(['active_business_id' => $business->id]);

        return [$owner, $organization, $business];
    }
}
