<?php

namespace App\Console\Commands;

use App\Services\OutboxService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessOutboxMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outbox:process {--batch-size=20 : عدد الرسائل في الدفعة} {--lease-seconds=60 : مدة حجز القفل بالثواني}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'معالجة الرسائل والأحداث المالية المعلقة في صندوق الإرسال اللحظي (Transactional Outbox)';

    /**
     * Execute the console command.
     */
    public function handle(OutboxService $outboxService): int
    {
        $batchSize = (int) $this->option('batch-size');
        $leaseSeconds = (int) $this->option('lease-seconds');

        $messages = $outboxService->claimBatch($batchSize, $leaseSeconds);

        if ($messages->isEmpty()) {
            $this->info('لا توجد رسائل معلقة في صندوق الإرسال اللحظي.');

            return self::SUCCESS;
        }

        $processedCount = 0;
        $failedCount = 0;

        foreach ($messages as $msg) {
            try {
                // تنفيذ تسليم الحدث أو الإشعار المقترن
                Log::info("Processing outbox message #{$msg->id} [{$msg->event_type}] for aggregate [{$msg->aggregate_type}:{$msg->aggregate_id}]", [
                    'message_uuid' => $msg->message_uuid,
                ]);

                $outboxService->markPublished($msg->id, (string) $msg->lease_token);
                $processedCount++;
            } catch (Exception $e) {
                Log::error("Failed to process outbox message #{$msg->id}: ".$e->getMessage());
                $outboxService->markFailed($msg->id, (string) $msg->lease_token, $e->getMessage());
                $failedCount++;
            }
        }

        $this->info("تمت معالجة {$processedCount} رسالة بنجاح، وفشل {$failedCount} رسالة.");

        return self::SUCCESS;
    }
}
