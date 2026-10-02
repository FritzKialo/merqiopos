<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

class BusinessFactory extends Factory
{
    protected $model = Business::class;

    public function definition(): array
    {
        return [
            'organization_id'  => null,
            'name'             => $this->faker->company(),
            'email'            => $this->faker->companyEmail(),
            'phone'            => $this->faker->phoneNumber(),
            'business_type'    => 'retail',
            'status'           => 'active',
            'payroll_settings' => [
                'enabled'    => true,
                'pay_cycle'  => 'monthly',
                'employer_pin' => null,
                'deductions' => ['paye' => true, 'nssf' => true, 'shif' => true],
            ],
        ];
    }

    public function withPayroll(array $overrides = []): static
    {
        return $this->state([
            'payroll_settings' => array_merge([
                'enabled'      => true,
                'pay_cycle'    => 'monthly',
                'employer_pin' => 'A001234567T',
                'deductions'   => ['paye' => true, 'nssf' => true, 'shif' => true],
            ], $overrides),
        ]);
    }
}
