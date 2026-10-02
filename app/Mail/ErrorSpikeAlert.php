<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/** One email per check-run covering every fingerprint that crossed its next alert threshold. */
class ErrorSpikeAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Collection $errors) {}

    public function envelope(): Envelope
    {
        $count = $this->errors->count();

        return new Envelope(
            subject: $count === 1
                ? 'Error spike: ' . $this->errors->first()->exception
                : "Error spikes: {$count} issues recurring",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.error-spike-alert', with: ['errors' => $this->errors]);
    }
}
