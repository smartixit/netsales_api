<?php

namespace App\Console\Commands;

use App\Models\UserSession;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * أمر تنظيف الجلسات المنتهية والمبطلة لتخفيف قاعدة البيانات.
 */
class CleanupUserSessions extends Command
{
    protected $signature = 'cleanup:user-sessions 
                            {--days=30 : Delete sessions expired or revoked older than this number of days}';

    protected $description = 'ينظف جلسات المستخدمين المنتهية والمبطلة القديمة من قاعدة البيانات';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::now('Asia/Aden')->subDays($days);

        $this->info("🚀 بدء تنظيف الجلسات المنتهية قبل {$cutoff->toDateTimeString()}...");

        $deletedCount = UserSession::where(function ($query) use ($cutoff) {
            $query->where('expires_at', '<', $cutoff)
                ->orWhere('revoked_at', '<', $cutoff);
        })->delete();

        $this->info("✅ تم تنظيف وحذف {$deletedCount} جلسة منتهية بنجاح.");

        return 0;
    }
}
