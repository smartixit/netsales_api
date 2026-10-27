<?php

namespace App\Jobs;

use App\Services\Support\EmailNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEmailNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $toEmail,
        public string $subject,
        public string $userName = '',
        public string $ctaLink = '',
        public string $body = ''
    ) {}

    public function handle(EmailNotificationService $emailService): void
    {
        $emailService->sendEmail(
            recipients: $this->toEmail,
            subject: $this->subject,
            body: $this->body,
            userName: $this->userName,
            ctaLink: $this->ctaLink
        );
    }
}
