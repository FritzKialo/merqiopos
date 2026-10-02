<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactEnquiry extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $enquiry
    ) {}

    public function envelope(): Envelope
    {
        $subjects = [
            'general'   => 'General Enquiry',
            'pricing'   => 'Pricing & Plans',
            'technical' => 'Technical Support',
            'demo'      => 'Demo Request',
            'other'     => 'Other',
        ];
        $label = $subjects[$this->enquiry['subject']] ?? 'Contact Form';

        return new Envelope(
            subject: "[Contact] {$label} — {$this->enquiry['name']}",
            replyTo: [$this->enquiry['email']],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-enquiry'
        );
    }
}
