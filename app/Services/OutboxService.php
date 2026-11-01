<?php

namespace App\Services;

use App\Models\OutboxMessage;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OutboxService
{
    /**
     * تسجيل رسالة أحداث جديدة في صندوق الإرسال اللحظي (Transactional Outbox) ضمن معاملة قاعدة البيانات الجارية.
     */
    public function record(
        string $aggregateType,
        int $aggregateId,
        string $eventType,
        array $payload,
        ?array $headers = null
    ): OutboxMessage {
        return OutboxMessage::create([
            'message_uuid' => (string) Str::uuid(),
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'event_type' => $eventType,
            'payload' => $payload,
            'headers' => $headers,
            'status' => 'PENDING',
            'retry_count' => 0,
            'max_retries' => 5,
        ]);
    }

    /**
     * حجز دفعة من الرسائل المعلقة للتنفيذ الآمن مع تخطي الصفوف المقفلة (SKIP LOCKED).
     *
     * @return Collection<int, OutboxMessage>
     */
    public function claimBatch(int $batchSize = 20, int $leaseSeconds = 60): Collection
    {
        return DB::transaction(function () use ($batchSize, $leaseSeconds): Collection {
            $now = Carbon::now('Asia/Aden');
            $leaseExpiry = $now->copy()->addSeconds($leaseSeconds);
            $leaseToken = (string) Str::uuid();

            // الاستعلام باستخدام SKIP LOCKED لمنع التنافس بين معالجي الطوابير المتعددين
            $messages = OutboxMessage::where(function ($query) use ($now) {
                $query->where('status', 'PENDING')
                    ->orWhere(function ($q) use ($now) {
                        $q->where('status', 'PROCESSING')
                            ->where('lease_expires_at', '<=', $now);
                    });
            })
                ->where('retry_count', '<', 5)
                ->orderBy('id', 'asc')
                ->limit($batchSize)
                ->lockForUpdate()
                ->get();

            if ($messages->isEmpty()) {
                return collect();
            }

            $ids = $messages->pluck('id')->toArray();

            DB::table('outbox_messages')
                ->whereIn('id', $ids)
                ->update([
                    'status' => 'PROCESSING',
                    'lease_token' => $leaseToken,
                    'lease_expires_at' => $leaseExpiry,
                    'updated_at' => $now,
                ]);

            foreach ($messages as $msg) {
                $msg->status = 'PROCESSING';
                $msg->lease_token = $leaseToken;
                $msg->lease_expires_at = $leaseExpiry;
            }

            return $messages;
        });
    }

    /**
     * وسم الرسالة كمكتملة الإرسال بنجاح (PUBLISHED).
     */
    public function markPublished(int $id, string $leaseToken): bool
    {
        $affected = DB::table('outbox_messages')
            ->where('id', $id)
            ->where('lease_token', $leaseToken)
            ->update([
                'status' => 'PUBLISHED',
                'lease_token' => null,
                'updated_at' => now('Asia/Aden'),
            ]);

        return $affected > 0;
    }

    /**
     * وسم الرسالة بالفشل مع إعادة المحاولة أو النقل إلى الحالة النهائية FAILED.
     */
    public function markFailed(int $id, string $leaseToken, string $errorMessage): bool
    {
        return DB::transaction(function () use ($id, $leaseToken, $errorMessage): bool {
            /** @var OutboxMessage|null $msg */
            $msg = OutboxMessage::where('id', $id)
                ->where('lease_token', $leaseToken)
                ->lockForUpdate()
                ->first();

            if (! $msg) {
                return false;
            }

            $newRetryCount = $msg->retry_count + 1;
            $newStatus = ($newRetryCount >= $msg->max_retries) ? 'FAILED' : 'PENDING';

            $msg->retry_count = $newRetryCount;
            $msg->status = $newStatus;
            $msg->lease_token = null;
            $msg->lease_expires_at = null;
            $msg->last_error = $errorMessage;
            $msg->updated_at = now('Asia/Aden');
            $msg->save();

            return true;
        });
    }
}
