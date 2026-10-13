<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\EmailService;

class SendEmailNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $toEmail;
    protected $subject;
    protected $body;
    protected $data;

    public function __construct(string $toEmail, string $subject, string $body, array $data = [])
    {
        $this->toEmail = $toEmail;
        $this->subject = $subject;
        $this->body = $body;
        $this->data = $data;
    }

    public function handle(EmailService $emailService): void
    {
        $emailService->sendImmediateEmail($this->toEmail, $this->subject, $this->body, $this->data);
    }
}
