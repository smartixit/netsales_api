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

    protected $toEmail;

    protected $subject;

    protected $body;

    protected $userName;

    protected $ctaLink;

    public function __construct(string $toEmail, string $subject, string $userName, string $ctaLink, string $body)
    {
        $this->toEmail = $toEmail;
        $this->subject = $subject;
        $this->userName = $userName;
        $this->ctaLink = $ctaLink;
        $this->body = $body;
    }

    public function handle(EmailNotificationService $emailService): void
    {
        $emailService->sendEmail($this->toEmail, $this->subject, $this->userName, $this->ctaLink, $this->body);
    }
}
