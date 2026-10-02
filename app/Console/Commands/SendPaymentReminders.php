<?php

namespace App\Console\Commands;

use App\Mail\PaymentReminderDigest;
use App\Models\Business;
use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendPaymentReminders extends Command
{
    protected $signature   = 'sme:payment-reminders';
    protected $description = 'Email business owners a weekly digest of customers with outstanding balances';

    public function handle(): void
    {
        // Was: ->with('owner') — Business::owner() isn't a real Eloquent
        // relation (it's a passthrough to organization?->owner()), so eager
        // loading it via with() threw a fatal "addEagerConstraints() on
        // null" error on every single run — this command has never
        // completed successfully. Load the branch owner the same
        // pivot-based way AutoRunPayroll/CheckNotifications already do
        // correctly elsewhere in this codebase.
            // Demo sandboxes (Support\DemoStore) look like real trial businesses — status='trial'
            // with genuinely overdue/low-stock data for realism — so they'd otherwise get
            // emailed here too, to their fake @demo.invalid owner address, which just bounces.
        $businesses = Business::where(function ($q) {
                $q->where('status', 'active')->orWhere('status', 'trial');
            })
            ->whereDoesntHave('organization', fn ($q) => $q->where('is_demo', true))
            ->get();

        $totalSent = 0;

        foreach ($businesses as $business) {
            $owner = $business->users()->wherePivot('role', 'owner')->first();
            if (! $owner?->email) continue;

            // payment_reminder_sent_at was written after every send but
            // never actually filtered on — this "weekly digest" command
            // would have re-emailed the exact same debtor list every single
            // time it ran, with no throttling at all. Mirrors the same
            // same-window debounce SendLowStockAlerts already does
            // correctly for its own sent_at column.
            $debtors = Customer::forBusiness($business->id)
                ->withDebt()
                ->where(function ($q) {
                    $q->whereNull('payment_reminder_sent_at')
                      ->orWhereDate('payment_reminder_sent_at', '<', now()->subDays(6));
                })
                ->orderByDesc('balance_owed')
                ->get();

            if ($debtors->isEmpty()) continue;

            Mail::to($owner->email)->queue(
                new PaymentReminderDigest($business, $debtors)
            );

            // Stamp sent_at on each customer
            Customer::whereIn('id', $debtors->pluck('id'))
                ->update(['payment_reminder_sent_at' => now()]);

            $this->info(
                "Digest sent: {$business->name} ({$owner->email}) — "
                . $debtors->count() . " customers, KSh "
                . number_format($debtors->sum('balance_owed'), 0) . " total"
            );

            $totalSent++;
        }

        $this->info("Done. Digests sent to {$totalSent} business(es).");
    }
}
