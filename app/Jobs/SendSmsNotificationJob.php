<?php

namespace App\Jobs;

use App\Enums\SmsMessageType;
use App\Services\Support\SMSGateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSmsNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  int|null  $recipientId  معرف الشبكة صاحبة الرسالة لتطبيق سياساتها وبوابتها الخاصة.
     */
    public function __construct(
        public string|array $phoneNumbers,
        public string $message,
        public int $priority = 0,
        public int $ttl = 604800,
        public ?int $recipientId = null,
        public SmsMessageType|string|null $messageType = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SMSGateService $smsGateService): void
    {
        $smsGateService->sendSms(
            $this->phoneNumbers,
            $this->message,
            $this->priority,
            $this->ttl,
            recipientId: $this->recipientId,
            messageType: $this->messageType,
        );
    }
}
