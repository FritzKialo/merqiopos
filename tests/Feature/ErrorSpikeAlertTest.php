<?php

namespace Tests\Feature;

use App\Mail\ErrorSpikeAlert;
use App\Models\ErrorLog;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * logs:check-error-spikes — emails the admin(s) once a bug's count climbs past
 * threshold within the alert window, and never re-alerts for the same occurrences.
 */
class ErrorSpikeAlertTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['security.error_alert_threshold' => 5, 'security.error_alert_window_minutes' => 15]);
        User::factory()->create(['is_super_admin' => true, 'email' => 'admin@merqiopos.com']);
    }

    private function makeError(array $overrides = []): ErrorLog
    {
        return ErrorLog::create(array_merge([
            'fingerprint'   => str_repeat('a', 40),
            'exception'     => 'RuntimeException',
            'message'       => 'Something broke',
            'method'        => 'GET',
            'path'          => '/reports',
            'count'         => 1,
            'alerted_count' => 0,
            'first_seen_at' => now()->subMinutes(10),
            'last_seen_at'  => now()->subMinutes(1),
        ], $overrides));
    }

    /** @test */
    public function a_fingerprint_crossing_the_threshold_sends_one_alert(): void
    {
        Mail::fake();

        $this->makeError(['count' => 6]);

        $this->artisan('logs:check-error-spikes')->run();

        Mail::assertQueued(ErrorSpikeAlert::class, 1);
    }

    /** @test */
    public function an_error_below_threshold_does_not_alert(): void
    {
        Mail::fake();

        $this->makeError(['count' => 2]);

        $this->artisan('logs:check-error-spikes')->run();

        Mail::assertNotQueued(ErrorSpikeAlert::class);
    }

    /** @test */
    public function a_resolved_error_never_alerts_even_with_a_high_count(): void
    {
        Mail::fake();

        $this->makeError(['count' => 50, 'resolved_at' => now()]);

        $this->artisan('logs:check-error-spikes')->run();

        Mail::assertNotQueued(ErrorSpikeAlert::class);
    }

    /** @test */
    public function an_error_outside_the_alert_window_does_not_alert(): void
    {
        Mail::fake();

        $this->makeError([
            'count' => 30,
            'first_seen_at' => now()->subDays(2),
            'last_seen_at'  => now()->subDays(1),
        ]);

        $this->artisan('logs:check-error-spikes')->run();

        Mail::assertNotQueued(ErrorSpikeAlert::class);
    }

    /** @test */
    public function it_does_not_realert_until_the_count_climbs_another_thresholds_worth(): void
    {
        Mail::fake();

        // Already alerted at count=8; only 2 more occurrences since (below the threshold of 5).
        $this->makeError(['count' => 10, 'alerted_count' => 8]);

        $this->artisan('logs:check-error-spikes')->run();

        Mail::assertNotQueued(ErrorSpikeAlert::class);
    }

    /** @test */
    public function it_realerts_once_the_count_climbs_another_thresholds_worth(): void
    {
        Mail::fake();

        $this->makeError(['count' => 20, 'alerted_count' => 10]);

        $this->artisan('logs:check-error-spikes')->run();

        Mail::assertQueued(ErrorSpikeAlert::class, 1);
    }

    /** @test */
    public function alerted_count_is_updated_so_a_second_run_with_no_new_occurrences_sends_nothing(): void
    {
        Mail::fake();

        $error = $this->makeError(['count' => 6]);

        $this->artisan('logs:check-error-spikes')->run();
        Mail::assertQueued(ErrorSpikeAlert::class, 1);

        $this->assertEquals(6, $error->fresh()->alerted_count);

        $this->artisan('logs:check-error-spikes')->run();
        Mail::assertQueued(ErrorSpikeAlert::class, 1); // still just the one send, not two
    }

    /** @test */
    public function multiple_spiking_errors_in_one_run_are_sent_as_a_single_email(): void
    {
        Mail::fake();

        $this->makeError(['fingerprint' => str_repeat('a', 40), 'count' => 6]);
        $this->makeError(['fingerprint' => str_repeat('b', 40), 'count' => 7]);

        $this->artisan('logs:check-error-spikes')->run();

        Mail::assertQueued(ErrorSpikeAlert::class, function ($mail) {
            return $mail->errors->count() === 2;
        });
    }
}
