<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BackupCompleted extends Mailable
{
    use Queueable, SerializesModels;

    /** @param string[] $files Absolute paths to the backup files to attach */
    public function __construct(
        public array $files,
        public \Illuminate\Support\Carbon $ranAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Merqio POS — Backup completed ' . $this->ranAt->format('d M Y'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.backup-completed');
    }

    public function attachments(): array
    {
        return array_map(fn (string $path) => Attachment::fromPath($path), $this->files);
    }
}
