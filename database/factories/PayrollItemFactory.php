<?php

namespace Database\Factories;

use App\Models\PayrollItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollItemFactory extends Factory
{
    protected $model = PayrollItem::class;

    public function definition(): array
    {
        $gross = 50000;
        $nssf  = 2160;
        $shif  = 1375;
        $paye  = 6735.35;

        return [
            'payroll_period_id' => null,
            'business_id'       => null,
            'user_id'           => null,
            'pay_type'          => 'retainer',
            'retainer_amount'   => $gross,
            'commission_rate'   => 0,
            'commission_sales'  => 0,
            'gross_pay'         => $gross,
            'nssf_employee'     => $nssf,
            'nssf_employer'     => $nssf,
            'shif_employee'     => $shif,
            'shif_employer'     => $shif,
            'paye'              => $paye,
            'total_deductions'  => round($nssf + $shif + $paye, 2),
            'net_pay'           => round($gross - $nssf - $shif - $paye, 2),
            'deduction_details' => ['taxable_income' => $gross - $nssf],
            'status'            => 'pending',
        ];
    }

    public function paid(): static
    {
        return $this->state(['status' => 'paid']);
    }
}
