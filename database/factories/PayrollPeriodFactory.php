<?php

namespace Database\Factories;

use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollPeriodFactory extends Factory
{
    protected $model = PayrollPeriod::class;

    public function definition(): array
    {
        $start = now()->startOfMonth();

        return [
            'business_id'   => null,
            'period_start'  => $start,
            'period_end'    => $start->copy()->endOfMonth(),
            'pay_cycle'     => 'monthly',
            'status'        => 'draft',
            'notes'         => null,
            'total_gross'   => 0,
            'total_nssf_ee' => 0,
            'total_nssf_er' => 0,
            'total_shif_ee' => 0,
            'total_shif_er' => 0,
            'total_paye'    => 0,
            'total_net'     => 0,
        ];
    }

    public function approved(): static
    {
        return $this->state(['status' => 'approved', 'approved_at' => now()]);
    }

    public function paid(): static
    {
        return $this->state(['status' => 'paid', 'approved_at' => now(), 'paid_at' => now()]);
    }
}
