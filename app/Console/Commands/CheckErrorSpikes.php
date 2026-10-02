<?php

namespace App\Console\Commands;

use App\Mail\ErrorSpikeAlert;
use App\Models\ErrorLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the admin(s) when an error is happening often, not just once. Runs every 15
 * minutes (routes/console.php). A fingerprint alerts again only once its count climbs
 * by another threshold's worth past the last time it alerted (alerted_count on the
 * row), so one persistently noisy bug sends one email per spike, not one every run.
 */
class CheckErrorSpikes extends Command
{
    protected $signature   = 'logs:check-error-spikes';
    protected $description = 'Email the admin(s) about errors that are recurring often';

    public function handle(): int
    {
        if (! config('security.dev_logging', true)) {
            return self::SUCCESS;
        }

        $threshold = max(1, (int) config('security.error_alert_threshold', 5));
        $window    = max(1, (int) config('security.error_alert_window_minutes', 15));

        $spiking = ErrorLog::whereNull('resolved_at')
            ->where('last_seen_at', '>=', now()->subMinutes($window))
            ->whereColumn('count', '>=', 'alerted_count')
            ->get()
            ->filter(fn (ErrorLog $e) => ($e->count - $e->alerted_count) >= $threshold);

        if ($spiking->isEmpty()) {
            $this->info('No error spikes.');

            return self::SUCCESS;
        }

        $to = config('support.admin_emails') ?: User::where('is_super_admin', true)->whereNotNull('email')->pluck('email')->all();

        if ($to) {
            $spiking->load('business');
            Mail::to($to)->queue(new ErrorSpikeAlert($spiking->values()));
        }

        ErrorLog::whereIn('id', $spiking->pluck('id'))->update(['alerted_count' => DB::raw('`count`'), 'alerted_at' => now()]);

        $this->info("Alerted on {$spiking->count()} spiking error(s).");

        return self::SUCCESS;
    }
}
