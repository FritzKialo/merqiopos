<?php

namespace App\Mail;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use App\Support\MailSafety;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuoteEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Quote $quote
    ) {}

    public function envelope(): Envelope
    {
        // Sender name stays the platform's ("Merqio POS"). Showing the business
        // name here with the platform's address made the hosting spam filter
        // (ImunifyEmail) quarantine these messages as spam — customers never
        // got them. The business is identified in the email itself.
        $business = $this->quote->business;

        // Reply-To only stays on the business's own address when that address is on the
        // platform's own domain — see MailSafety for why a mismatched domain gets quarantined.
        // Otherwise the customer still reaches the shop via the contact details already shown
        // in the email body (business-footer.blade.php).
        return new Envelope(
            replyTo: MailSafety::replyTo($business->email, $business->name),
            subject: "Quotation #{$this->quote->quote_number} from {$business->name}"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quote'
        );
    }

    // Unlike InvoiceEmail (empty attachments(), and a "View Invoice" link
    // that points at a staff-only route no customer can actually reach),
    // this attaches the same PDF the Print button generates — the customer
    // gets a real, complete, standalone document with no login required.
    public function attachments(): array
    {
        $pdf = Pdf::loadView('quotes.pdf', ['quote' => $this->quote])
            ->setPaper('a4', 'portrait');

        return [
            Attachment::fromData(fn () => $pdf->output(), "Quote-{$this->quote->quote_number}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
