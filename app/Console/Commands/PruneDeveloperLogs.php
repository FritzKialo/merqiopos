<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\ErrorLog;
use Illuminate\Console\Command;

class PruneDeveloperLogs extends Command
{
    protected $signature   = 'logs:prune {--days= : Keep this many days (default: config security.log_retention_days)}';
    protected $description = 'Delete developer activity and error logs older than the retention period.';

    public function handle(): int
    {
        $days   = (int) ($this->option('days') ?: config('security.log_retention_days', 60));
        $cutoff = now()->subDays(max(7, $days));

        $activity = ActivityLog::where('created_at', '<', $cutoff)->delete();
        $errors   = ErrorLog::where('last_seen_at', '<', $cutoff)->delete();

        $this->info("Pruned {$activity} activity rows and {$errors} error rows older than {$days} days.");

        return self::SUCCESS;
    }
}
