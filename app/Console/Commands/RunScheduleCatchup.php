<?php

namespace App\Console\Commands;

use App\Services\SmartSchedulerCatchupService;
use Illuminate\Console\Command;

/**
 * أمر تشغيل محرك التعويض الذكي للمهام المجدولة الفائتة.
 */
class RunScheduleCatchup extends Command
{
    protected $signature = 'schedule:catchup 
                            {--force : Force execution of all tasks regardless of last run time}
                            {--task= : Run only a specific task key or command}';

    protected $description = 'ينفذ المهام المجدولة الفائتة تلقائياً عبر محرك التعويض الذكي (Smart Scheduler Catch-up Engine)';

    public function handle(SmartSchedulerCatchupService $service): int
    {
        $force = (bool) $this->option('force');
        $task = $this->option('task');

        $this->info('🚀 بدء فحص وتشغيل محرك التعويض الذكي للمهام المجدولة...');
        if ($force) {
            $this->warn('⚠️ تم تفعيل وضع الإجبار (--force): سيتم تشغيل المهام بغض النظر عن تاريخ التنفيذ السابق.');
        }

        $result = $service->runAllCatchupTasks($force, $task);

        if (! empty($result['executed'])) {
            $this->info('✅ المهام التي تم تنفيذها:');
            foreach ($result['executed'] as $key => $item) {
                $statusIcon = $item['status'] === 'success' ? '✔' : '✖';
                $this->line("  [{$statusIcon}] {$item['name']} ({$item['command']}): {$item['status']}");
                if (! empty($item['output'])) {
                    $this->comment('      المخرجات: '.$item['output']);
                }
            }
        } else {
            $this->info('ℹ️ لا توجد أي مهام مجدولة فائتة أو مستحقة حالياً.');
        }

        return 0;
    }
}
