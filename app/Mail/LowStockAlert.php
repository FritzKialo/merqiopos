<?php

namespace App\Mail;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LowStockAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Business   $business,
        public Collection $products,
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->products->count();
        $noun  = $count === 1 ? 'product' : 'products';
        return new Envelope(
            subject: "[{$this->business->name}] {$count} {$noun} running low on stock"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.low-stock-alert'
        );
    }
}
