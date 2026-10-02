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

class SaleReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Sale $sale,
        public string $link
    ) {}

    public function envelope(): Envelope
    {
        $business = $this->sale->business;
        $fromName = $business->name ?: config('mail.from.name');

        // The "From" address previously used the business's own arbitrary
        // email (e.g. sales@theirshop.co.ke) instead of the platform's
        // verified sending domain — most SMTP relays/mail providers reject
        // (or silently drop) a From address on a domain they haven't
        // authorized to send as, which is exactly what was happening here:
        // every receipt with a business email on file failed to send, with
        // ReceiptService's caught-and-logged exception invisible in
        // production because LOG_LEVEL only records error and above, not
        // the warning() call it uses. From now stays on the platform's own
        // authorized address; Reply-To still points at the business so
        // customer replies reach the shop directly — that part worked fine
        // since Reply-To doesn't go through the same sender-authorization
        // check as From. It only stays on when that address is on the platform's
        // own domain, though — see MailSafety — otherwise it's dropped and the
        // customer reaches the shop via the contact details already in the receipt.
        return new Envelope(
            replyTo: MailSafety::replyTo($business->email, $fromName),
            subject: "Your receipt {$this->sale->invoice_number} — {$business->name}"
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.sale-receipt');
    }
}
