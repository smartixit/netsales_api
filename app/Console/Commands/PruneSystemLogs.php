<?php

namespace App\Console\Commands;

use App\Interfaces\SystemLogRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * أمر تنظيف وأرشفة سجلات النظام القديمة لتخفيف قاعدة البيانات.
 */
class PruneSystemLogs extends Command
{
    protected $signature = 'system:prune-logs 
                            {--days= : Delete logs older than this number of days (defaults to system setting)}';

    protected $description = 'يحذف سجلات النظام القديمة للحفاظ على سرعة ورشاقة قاعدة البيانات';

    public function handle(SystemLogRepositoryInterface $logRepository): int
    {
        $defaultDays = (int) systemSetting('system_logs_retention_days', 60);
        $days = (int) ($this->option('days') ?: $defaultDays);
        $cutoff = Carbon::now('Asia/Aden')->subDays($days);

        $this->info("🚀 بدء تنظيف سجلات النظام الأقدم من {$days} يوماً (قبل {$cutoff->toDateTimeString()})...");

        $deletedCount = $logRepository->pruneOlderThan($cutoff);

        $this->info("✅ تم تنظيف وحذف {$deletedCount} سجلاً قديماً بنجاح.");

        return 0;
    }
}
