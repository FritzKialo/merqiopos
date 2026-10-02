<?php

namespace Database\Factories;

use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaffProfileFactory extends Factory
{
    protected $model = StaffProfile::class;

    public function definition(): array
    {
        return [
            'user_id'          => null,
            'business_id'      => null,
            'pay_type'         => 'retainer',
            'retainer_amount'  => 50000,
            'commission_rate'  => 0,
            'kra_pin'          => strtoupper($this->faker->bothify('?##########?')),
            'nssf_no'          => $this->faker->numerify('#######'),
            'id_number'        => $this->faker->numerify('########'),
            'job_title'        => $this->faker->jobTitle(),
            'employment_date'  => now()->subYear(),
            'termination_date' => null,
            'deduction_overrides' => null,
        ];
    }

    public function retainer(float $amount = 50000): static
    {
        return $this->state([
            'pay_type'        => 'retainer',
            'retainer_amount' => $amount,
            'commission_rate' => 0,
        ]);
    }

    public function commission(float $rate = 5.0): static
    {
        return $this->state([
            'pay_type'        => 'commission',
            'retainer_amount' => 0,
            'commission_rate' => $rate,
        ]);
    }

    public function hybrid(float $retainer = 20000, float $rate = 3.0): static
    {
        return $this->state([
            'pay_type'        => 'hybrid',
            'retainer_amount' => $retainer,
            'commission_rate' => $rate,
        ]);
    }
}
