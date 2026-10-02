<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name'              => $this->faker->company(),
            'owner_user_id'     => null, // set after owner user is created
            'subscription_plan' => 'growth',
            'status'            => 'active',
            'trial_ends_at'     => null,
        ];
    }

    public function onTrial(): static
    {
        return $this->state([
            'status'        => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function solo(): static
    {
        return $this->state(['subscription_plan' => 'solo']);
    }

    public function growth(): static
    {
        return $this->state(['subscription_plan' => 'growth']);
    }

    public function scale(): static
    {
        return $this->state(['subscription_plan' => 'scale']);
    }
}
