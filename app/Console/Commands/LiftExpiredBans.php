<?php

namespace App\Console\Commands;

use App\Models\SecurityBan;
use App\Services\SystemLoggerService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * أمر رفع الحظر الأمني التلقائي عن المستخدمين أو الأجهزة بعد انتهاء مدة الحظر.
 */
class LiftExpiredBans extends Command
{
    protected $signature = 'security:lift-expired-bans';

    protected $description = 'يرفع الحظر الأمني المنتهي تلقائياً عن المستخدمين والأجهزة';

    public function handle(): int
    {
        $now = Carbon::now('Asia/Aden');

        $this->info("🚀 فحص الحظر الأمني المنتهي قبل {$now->toDateTimeString()}...");

        $expiredBans = SecurityBan::whereNotNull('end_at')
            ->where('end_at', '<=', $now)
            ->get();

        if ($expiredBans->isEmpty()) {
            $this->info('ℹ️ لا يوجد أي حظر أمني منتهي الصلاحية حالياً.');

            return 0;
        }

        $count = 0;
        foreach ($expiredBans as $ban) {
            $ban->delete(); // Soft delete as defined on SecurityBan
            $count++;

            SystemLoggerService::log(
                module: 'security',
                action: 'lift_expired_ban',
                entityType: 'SecurityBan',
                entityId: $ban->ban_id,
                newData: [
                    'user_id' => $ban->user_id,
                    'device_id' => $ban->device_id,
                    'end_at' => $ban->end_at?->toDateTimeString(),
                ]
            );
        }

        $this->info("✅ تم رفع الحظر الأمني عن {$count} مستخدم/جهاز بنجاح.");

        return 0;
    }
}
