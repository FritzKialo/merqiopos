<?php

namespace App\Mail;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class PaymentReminderDigest extends Mailable
{
    use Queueable, SerializesModels;

    public float $totalOwed;

    public function __construct(
        public Business   $business,
        public Collection $customers,
    ) {
        $this->totalOwed = (float) $customers->sum('balance_owed');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[{$this->business->name}] Weekly Debt Digest — KSh " . number_format($this->totalOwed, 0) . " outstanding"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-reminder-digest'
        );
    }
}
