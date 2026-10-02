<?php
namespace App\Services;

use App\Models\Customer;
use App\Models\LoyaltyProgram;

class LoyaltyService
{
    private function defaultTiers(): array
    {
        return [
            ['name' => 'Bronze', 'min_points' => 0,    'multiplier' => 1.0, 'discount_pct' => 0,  'perks' => 'Basic membership'],
            ['name' => 'Silver', 'min_points' => 1000,  'multiplier' => 1.5, 'discount_pct' => 5,  'perks' => '5% discount on all purchases'],
            ['name' => 'Gold',   'min_points' => 5000,  'multiplier' => 2.0, 'discount_pct' => 10, 'perks' => '10% discount + priority service'],
        ];
    }

    public function getTiers(LoyaltyProgram $program): array
    {
        return $program->tiers ?? $this->defaultTiers();
    }

    public function getTier(Customer $customer, LoyaltyProgram $program): ?array
    {
        $tiers = $this->getTiers($program);
        $points = $customer->loyalty_points ?? 0;
        $current = null;
        foreach ($tiers as $tier) {
            if ($points >= $tier['min_points']) $current = $tier;
        }
        return $current;
    }

    public function updateTier(Customer $customer, LoyaltyProgram $program): void
    {
        $tier = $this->getTier($customer, $program);
        if ($tier) $customer->update(['loyalty_tier' => $tier['name']]);
    }

    public function getMultiplier(Customer $customer, LoyaltyProgram $program): float
    {
        $tier = $this->getTier($customer, $program);
        return $tier ? ($tier['multiplier'] ?? 1.0) : 1.0;
    }

    public function awardPoints(Customer $customer, float $saleAmount, LoyaltyProgram $program): float
    {
        $basePoints = $saleAmount * ($program->points_per_shilling ?? 1);
        $multiplier = $this->getMultiplier($customer, $program);
        $earned = round($basePoints * $multiplier, 2);
        $customer->increment('loyalty_points', $earned);
        $customer->refresh();
        $this->updateTier($customer, $program);
        return $earned;
    }
}
