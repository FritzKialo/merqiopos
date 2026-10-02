<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Organization $organization,
        public Subscription $subscription
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Subscription Confirmed — Merqio POS'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-confirmed'
        );
    }
}
