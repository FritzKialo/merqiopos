<?php

namespace Tests\Feature;

use App\Models\StaffProfile;
use App\Models\User;
use Tests\Feature\Helpers\CreatesOrganization;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use CreatesOrganization;

    /** @test */
    public function owner_can_list_staff(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();

        $staff = User::factory()->cashier()->create();
        $business->users()->attach($staff->id, ['role' => 'cashier']);

        $this->actingAs($owner)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertSee($staff->name);
    }

    /** @test */
    public function owner_can_view_staff_profile(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();

        $staff = User::factory()->cashier()->create();
        $business->users()->attach($staff->id, ['role' => 'cashier']);

        $this->actingAs($owner)
            ->get(route('staff.show', $staff))
            ->assertOk();
    }

    /** @test */
    public function owner_can_save_staff_profile(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();

        $staff = User::factory()->cashier()->create();
        $business->users()->attach($staff->id, ['role' => 'cashier']);

        $this->actingAs($owner)->post(route('staff.profile.update', $staff), [
            'pay_type'        => 'retainer',
            'retainer_amount' => 45000,
            'commission_rate' => 0,
            'kra_pin'         => 'A001234567P',
            'nssf_no'         => '1234567',
            'id_number'       => '12345678',
            'job_title'       => 'Sales Associate',
            'employment_date' => '2024-01-01',
        ]);

        $this->assertDatabaseHas('staff_profiles', [
            'user_id'     => $staff->id,
            'business_id' => $business->id,
            'pay_type'    => 'retainer',
        ]);
    }

    /** @test */
    public function staff_profile_is_upserted_not_duplicated(): void
    {
        [$owner, , $business] = $this->scaffoldOrg();

        $staff = User::factory()->cashier()->create();
        $business->users()->attach($staff->id, ['role' => 'cashier']);

        $data = [
            'pay_type'        => 'retainer',
            'retainer_amount' => 40000,
            'commission_rate' => 0,
            'employment_date' => '2024-01-01',
        ];

        $this->actingAs($owner)->post(route('staff.profile.update', $staff), $data);
        $this->actingAs($owner)->post(route('staff.profile.update', $staff), array_merge($data, [
            'retainer_amount' => 50000,
        ]));

        $this->assertEquals(1, StaffProfile::where([
            'user_id'     => $staff->id,
            'business_id' => $business->id,
        ])->count());

        $this->assertEquals(50000, StaffProfile::where([
            'user_id'     => $staff->id,
            'business_id' => $business->id,
        ])->value('retainer_amount'));
    }

    /** @test */
    public function owner_cannot_view_staff_from_another_business(): void
    {
        [$owner] = $this->scaffoldOrg();

        $outsider = User::factory()->cashier()->create();
        // outsider is NOT attached to $owner's business

        $this->actingAs($owner)
            ->get(route('staff.show', $outsider))
            ->assertForbidden();
    }
}
