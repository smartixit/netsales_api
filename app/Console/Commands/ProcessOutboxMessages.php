<?php

namespace App\Console\Commands;

use App\Services\OutboxService;
use App\Services\Billing\BillingOutboxNotificationDispatcher;
use Throwable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessOutboxMessages extends Command
{
    protected $signature = 'outbox:process {--batch-size=20 : عدد الرسائل في الدفعة} {--lease-seconds=60 : مدة حجز القفل بالثواني}';

    protected $description = 'معالجة الرسائل والأحداث المالية المعلقة في صندوق الإرسال اللحظي (Transactional Outbox)';

    public function handle(
        OutboxService $outboxService,
        BillingOutboxNotificationDispatcher $billingNotifications
    ): int {
        $batchSize = max(1, min((int) $this->option('batch-size'), 500));
        $leaseSeconds = max(10, min((int) $this->option('lease-seconds'), 3600));
        $messages = $outboxService->claimBatch($batchSize, $leaseSeconds);

        if ($messages->isEmpty()) {
            $this->info('لا توجد رسائل معلقة في صندوق الإرسال اللحظي.');

            return self::SUCCESS;
        }

        $processedCount = 0;
        $failedCount = 0;
        $billingEventTypes = [
            'billing.invoice.payment_reminder',
            'billing.subscription.renewal_reminder',
        ];

        foreach ($messages as $message) {
            try {
                Log::info("Processing outbox message #{$message->id} [{$message->event_type}] for aggregate [{$message->aggregate_type}:{$message->aggregate_id}]", [
                    'message_uuid' => $message->message_uuid,
                ]);

                if (in_array($message->event_type, $billingEventTypes, true)
                    && ! $billingNotifications->dispatch($message)) {
                    throw new \RuntimeException('تعذر تسليم إشعار الفوترة إلى المستلم.');
                }

                $outboxService->markPublished($message->id, (string) $message->lease_token);
                $processedCount++;
            } catch (Throwable $exception) {
                Log::error("Failed to process outbox message #{$message->id}: ".$exception->getMessage(), [
                    'message_uuid' => $message->message_uuid,
                ]);
                $outboxService->markFailed($message->id, (string) $message->lease_token, $exception->getMessage());
                $failedCount++;
            }
        }

        $this->info("تمت معالجة {$processedCount} رسالة بنجاح، وفشل {$failedCount} رسالة.");

        return $failedCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
