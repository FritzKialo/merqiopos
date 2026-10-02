<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates the organization + first store for a user who doesn't have one
 * yet, and attaches them as its owner. Shared by password registration
 * (RegisterController) and the post-Google-signup onboarding step
 * (OnboardingController) so both paths create an identical, atomically
 * committed account shape — including the audit log entry, which must land
 * in the same transaction as everything else so a failure here rolls back
 * the whole thing instead of leaving a half-created account behind.
 */
class BusinessAccountProvisioner
{
    public function provision(User $user, array $businessData, ?string $ip): Business
    {
        return DB::transaction(function () use ($user, $businessData, $ip) {
            $organization = Organization::create([
                'name'              => $businessData['business_name'],
                'owner_user_id'     => $user->id,
                'subscription_plan' => 'solo',
                'status'            => 'trial',
                // 1 month — only Solo (the entry tier) carries a trial at
                // all; Growth/Enterprise are paid from day one.
                'trial_ends_at'     => now()->addMonth(),
            ]);

            $user->update(['organization_id' => $organization->id]);

            $business = Business::create([
                'organization_id'   => $organization->id,
                'name'              => $businessData['business_name'],
                'email'             => $businessData['business_email'],
                'phone'             => $businessData['business_phone'],
                'address'           => $businessData['business_address'] ?? null,
                'city'              => $businessData['business_city'] ?? null,
                'industry'          => $businessData['industry'] ?? null,
                'business_type'     => $businessData['business_type'] ?? null,
                'subscription_plan' => 'starter',
                'status'            => 'trial',
                'trial_ends_at'     => now()->addMonth(),
            ]);

            $business->users()->attach($user->id, ['role' => 'owner']);

            AuditLog::create([
                'business_id'  => $business->id,
                'user_id'      => $user->id,
                'event'        => 'organization.registered',
                'subject_type' => Organization::class,
                'subject_id'   => $organization->id,
                'metadata'     => ['plan' => 'trial'],
                'ip_address'   => $ip,
                'created_at'   => now(),
            ]);

            return $business;
        });
    }
}
