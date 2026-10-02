<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily backup (database + uploaded files) — 1am Nairobi time (UTC+3 =
// 22:00 UTC the previous day), well outside business hours so a slow
// mysqldump on a large database doesn't compete with real traffic.
// withoutOverlapping guards against a backup that runs long overlapping
// with the next day's run.
Schedule::command('sme:backup')->dailyAt('22:00')->withoutOverlapping();

// Send expiry warning emails daily at 8am Nairobi time (UTC+3 = 05:00 UTC).
// This was ALSO independently registered from bootstrap/app.php's separate
// ->withSchedule() hook at this exact same time — meaning every customer
// whose trial/subscription was expiring in exactly 3 days has been getting
// this email twice a day, every day, in production (SendSubscriptionWarnings
// has no de-dup guard of its own). Removed the duplicate there; this file is
// now the single source of truth for all scheduled commands.
Schedule::command('sme:subscription-warnings')->dailyAt('05:00');

// Recurring invoices: generate sales for any due today. This command existed
// but was never actually scheduled here when originally fixed this session —
// turned out it WAS already independently scheduled via bootstrap/app.php's
// separate mechanism, at a different time (06:00 UTC vs this file's 03:00),
// so it's actually been running twice a day since that fix shipped (likely
// harmless in practice since generation advances next_run_date past today on
// the first run, but still two live, disagreeing registrations). Removed the
// duplicate. Runs daily at 6am Nairobi time (UTC+3 = 03:00 UTC).
Schedule::command('sme:process-recurring-invoices')->dailyAt('03:00');

// Auto-payroll: run daily at 6am Nairobi time (UTC+3 = 03:00 UTC)
// Businesses with auto_payroll enabled and today matching their pay_day will be processed.
Schedule::command('payroll:auto-run')->dailyAt('03:00');

// Expiry alerts: run daily at 7:00am
Schedule::command('inventory:expiry-alerts')->dailyAt('07:00');

// Reorder check: auto-draft POs for products at or below reorder level, daily at 8am Nairobi (05:00 UTC)
Schedule::command('inventory:reorder-check')->dailyAt('05:00');

// System notifications: check low stock, overdue invoices, etc. hourly
Schedule::command('notifications:check')->hourly();

// Low-stock alert emails: WAS already scheduled, but from bootstrap/app.php's
// separate ->withSchedule() hook — a second, independent registration
// mechanism that was also active alongside this file (see the note left in
// bootstrap/app.php). Consolidated here as the single source of truth. Its
// own fatal bug (Business::owner() isn't a real relation — see the command)
// meant it crashed on every run regardless of being scheduled. Its own daily
// same-day debounce (low_stock_alert_sent_at) means daily is the right
// cadence. Runs daily at 7am Nairobi time (UTC+3 = 04:00 UTC) — matches the
// time originally intended in bootstrap/app.php's comment.
Schedule::command('sme:low-stock-alerts')->dailyAt('04:00');

// Payment reminder digest: same story as low-stock-alerts above — was
// already scheduled via bootstrap/app.php's duplicate mechanism (identical
// time, so no functional double-send from timing, but still two live
// event registrations for the same run). Consolidated here. Fixed its own
// fatal bug and its previously-dead 6-day debounce (see the command).
// Runs Monday 8am Nairobi time (UTC+3 = 05:00 UTC).
Schedule::command('sme:payment-reminders')->weeklyOn(1, '05:00');

// Developer logs: delete activity and error logs older than the retention period
// (config('security.log_retention_days'), 60 days by default). 23:30 UTC = 02:30 Nairobi.
Schedule::command('logs:prune')->dailyAt('23:30');

// Demo sandboxes live 24 hours.
Schedule::command('demo:purge')->hourly();

// Error-spike alerts: check every 15 minutes for anything recurring often.
Schedule::command('logs:check-error-spikes')->everyFifteenMinutes();
