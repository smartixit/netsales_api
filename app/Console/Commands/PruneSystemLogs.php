<?php

namespace App\Console\Commands;

use App\Models\SystemLog;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * أمر تنظيف وأرشفة سجلات النظام القديمة لتخفيف قاعدة البيانات.
 */
class PruneSystemLogs extends Command
{
    protected $signature = 'system:prune-logs 
                            {--days=60 : Delete logs older than this number of days}';

    protected $description = 'يحذف سجلات النظام القديمة للحفاظ على سرعة ورشاقة قاعدة البيانات';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::now('Asia/Aden')->subDays($days);

        $this->info("🚀 بدء تنظيف سجلات النظام الأقدم من {$days} يوماً (قبل {$cutoff->toDateTimeString()})...");

        $deletedCount = SystemLog::where('log_date', '<', $cutoff)->delete();

        $this->info("✅ تم تنظيف وحذف {$deletedCount} سجلاً قديماً بنجاح.");

        return 0;
    }
}
