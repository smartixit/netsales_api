<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GenericNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $emailData
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailData['MessageTitle'] ?? 'إشعار من NetSales',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification',
            with: $this->emailData,
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
