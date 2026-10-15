<?php

namespace App\Jobs;

use App\Services\Support\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * وظيفة طابور لإرسال إشعارات FCM الفورية بشكل غير متزامن (Asynchronous FCM Push Job).
 */
class SendFcmNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param array<string, mixed> $data
     * @param array<int, string>|string|null $tokens
     */
    public function __construct(
        public ?int $userId,
        public string $title,
        public string $body,
        public array $data = [],
        public ?string $image = null,
        public array|string|null $tokens = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(FcmService $fcmService): void
    {
        if ($this->userId !== null) {
            $fcmService->sendToUser(
                userId: $this->userId,
                title: $this->title,
                body: $this->body,
                data: $this->data,
                image: $this->image
            );
        } elseif (! empty($this->tokens)) {
            $tokensList = is_array($this->tokens) ? $this->tokens : [$this->tokens];
            $fcmService->sendToTokens(
                tokens: $tokensList,
                title: $this->title,
                body: $this->body,
                data: $this->data,
                image: $this->image
            );
        }
    }
}
