<?php

namespace App\Mail;

use App\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use App\Support\MailSafety;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Sale $sale
    ) {}

    public function envelope(): Envelope
    {
        // Sender name stays the platform's ("Merqio POS"). Showing the business
        // name here with the platform's address made the hosting spam filter
        // (ImunifyEmail) quarantine these messages as spam — customers never
        // got them. The business is identified in the email itself.
        $business = $this->sale->business;

        // Reply-To only stays on the business's own address when that address is on the
        // platform's own domain — see MailSafety for why a mismatched domain gets quarantined.
        // Otherwise the customer still reaches the shop via the contact details already shown
        // in the email body (business-footer.blade.php).
        return new Envelope(
            replyTo: MailSafety::replyTo($business->email, $business->name),
            subject: "Invoice #{$this->sale->invoice_number} from {$business->name}"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice'
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
