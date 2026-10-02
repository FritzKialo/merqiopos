<?php

namespace App\Mail;

use App\Models\SupportConversation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** "A tenant wrote to support" — sent to the platform admin. */
class SupportMessageAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SupportConversation $conversation, public string $preview) {}

    public function envelope(): Envelope
    {
        $store = $this->conversation->business?->name ?? 'a store';

        return new Envelope(subject: '[Support] New message from ' . $store);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.support-alert');
    }
}
