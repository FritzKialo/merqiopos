<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionExpiringSoon;
use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionWarnings extends Command
{
    protected $signature   = 'sme:subscription-warnings';
    protected $description = 'Email owners whose organization trial or subscription expires in 3 days';

    public function handle(): void
    {
        $warningDays = 3;
        $targetDate  = now()->addDays($warningDays)->toDateString();

        // Organizations whose active subscription ends in exactly 3 days.
        // Subscriptions are org-level: one subscription covers every branch.
            // Demo sandboxes (Support\DemoStore) look like real trial businesses — status='trial'
            // with genuinely overdue/low-stock data for realism — so they'd otherwise get
            // emailed here too, to their fake @demo.invalid owner address, which just bounces.
        $expiringSubs = Organization::where('is_demo', false)
            ->whereHas('subscriptions', function ($q) use ($targetDate) {
                $q->where('status', 'active')
                  ->whereDate('end_date', $targetDate);
            })
            ->with(['owner', 'subscriptions' => function ($q) use ($targetDate) {
                $q->where('status', 'active')
                  ->whereDate('end_date', $targetDate);
            }])
            ->get();

        foreach ($expiringSubs as $org) {
            $owner = $org->owner;
            if (! $owner?->email) continue;

            $subscription = $org->subscriptions->first();

            Mail::to($owner->email)->queue(
                new SubscriptionExpiringSoon(
                    $org,
                    $subscription->end_date->format('d M Y'),
                    $warningDays
                )
            );

            $this->info("Subscription warning sent: {$org->name} ({$owner->email})");
        }

        // Organizations on trial ending in exactly 3 days.
        $expiringTrials = Organization::where('status', 'trial')
            ->where('is_demo', false)
            ->whereDate('trial_ends_at', $targetDate)
            ->with('owner')
            ->get();

        foreach ($expiringTrials as $org) {
            $owner = $org->owner;
            if (! $owner?->email) continue;

            Mail::to($owner->email)->queue(
                new SubscriptionExpiringSoon(
                    $org,
                    $org->trial_ends_at->format('d M Y'),
                    $warningDays
                )
            );

            $this->info("Trial warning sent: {$org->name} ({$owner->email})");
        }

        $total = $expiringSubs->count() + $expiringTrials->count();
        $this->info("Done. {$total} warning(s) sent.");
    }
}
