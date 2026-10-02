<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\PayrollPeriod;
use App\Services\PayrollCalculationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoRunPayroll extends Command
{
    protected $signature   = 'payroll:auto-run {--dry-run : List what would run without making changes}';
    protected $description = 'Create, calculate (and optionally approve/pay) payroll periods for businesses with auto-payroll enabled.';

    public function __construct(private PayrollCalculationService $calculator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $today   = now()->day;
        $dry     = $this->option('dry-run');

        $businesses = Business::whereNotNull('payroll_settings')
            ->get()
            ->filter(fn ($b) => $b->isPayrollEnabled() && $b->autoPayrollEnabled() && $b->payDay() === $today);

        if ($businesses->isEmpty()) {
            $this->info('No businesses scheduled for auto-payroll today.');
            return 0;
        }

        $this->info("Running auto-payroll for {$businesses->count()} business(es) — day {$today}." . ($dry ? ' [DRY RUN]' : ''));

        foreach ($businesses as $business) {
            $this->processBusinesses($business, $dry);
        }

        return 0;
    }

    private function processBusinesses(Business $business, bool $dry): void
    {
        $mode     = $business->autoPayrollMode();
        $payCycle = $business->payCycle();

        [$start, $end] = $this->periodDates($payCycle);

        // Skip if a period already exists for this range
        $exists = PayrollPeriod::forBusiness($business->id)
            ->where('period_start', $start->toDateString())
            ->where('period_end',   $end->toDateString())
            ->exists();

        if ($exists) {
            $this->line("  [{$business->name}] Period {$start->toDateString()}–{$end->toDateString()} already exists. Skipping.");
            return;
        }

        // Must have at least one active staff with a profile
        $staffCount = $business->staffProfiles()
            ->whereNull('termination_date')
            ->count();

        if ($staffCount === 0) {
            $this->line("  [{$business->name}] No active staff profiles. Skipping.");
            return;
        }

        if ($dry) {
            $this->line("  [{$business->name}] Would create {$payCycle} period {$start->toDateString()}–{$end->toDateString()} (mode: {$mode}, staff: {$staffCount}).");
            return;
        }

        try {
            DB::beginTransaction();

            $period = PayrollPeriod::create([
                'business_id'  => $business->id,
                'period_start' => $start->toDateString(),
                'period_end'   => $end->toDateString(),
                'pay_cycle'    => $payCycle,
                'status'       => 'draft',
                'notes'        => 'Auto-created by scheduled payroll.',
            ]);

            // Always calculate
            $items = $this->calculator->calculatePeriod($period);
            $this->line("  [{$business->name}] Period created, {$items->count()} item(s) calculated.");

            if (in_array($mode, ['auto_approve', 'fully_auto'])) {
                $period->update(['status' => 'approved', 'approved_at' => now()]);
                $this->line("  [{$business->name}] Auto-approved.");
            }

            if ($mode === 'fully_auto') {
                $period->items()->where('status', 'pending')->update(['status' => 'paid']);
                $period->update(['status' => 'paid', 'paid_at' => now()]);

                // Post expense
                $alreadyPosted = \App\Models\Expense::where('business_id', $period->business_id)
                    ->where('reference', 'PAYROLL-' . $period->id)
                    ->exists();

                if (! $alreadyPosted) {
                    $category = \App\Models\ExpenseCategory::firstOrCreate(
                        ['business_id' => $period->business_id, 'name' => 'Salaries & Wages'],
                        ['description' => 'Payroll disbursements']
                    );

                    \App\Models\Expense::create([
                        'business_id'         => $period->business_id,
                        'expense_category_id' => $category->id,
                        'user_id'             => $business->users()->where('role', 'owner')->first()?->id ?? $business->users()->first()?->id,
                        'title'               => 'Payroll: ' . $period->period_start->format('d M') . ' – ' . $period->period_end->format('d M Y'),
                        'description'         => count($items) . ' employee(s), auto-paid.',
                        'amount'              => $period->total_net,
                        'payment_method'      => 'bank_transfer',
                        'reference'           => 'PAYROLL-' . $period->id,
                        'expense_date'        => $period->paid_at->toDateString(),
                    ]);
                }

                $this->line("  [{$business->name}] Auto-paid and expense posted (KSh " . number_format($period->total_net, 0) . ').');
            }

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("  [{$business->name}] Failed: " . $e->getMessage());
            Log::error("AutoRunPayroll [{$business->id}]: " . $e->getMessage());
        }
    }

    private function periodDates(string $payCycle): array
    {
        $today = now();

        $start = match ($payCycle) {
            'weekly'    => $today->copy()->startOfWeek(),
            'bi_weekly' => $today->copy()->startOfWeek(),
            default     => $today->copy()->startOfMonth(),
        };

        $end = match ($payCycle) {
            'weekly'    => $today->copy()->endOfWeek(),
            'bi_weekly' => $today->copy()->startOfWeek()->addDays(13),
            default     => $today->copy()->endOfMonth(),
        };

        return [$start, $end];
    }
}
