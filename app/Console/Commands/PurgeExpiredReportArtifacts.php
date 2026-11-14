<?php

namespace App\\Console\\Commands;

use App\\Models\\ReportRun;
use Illuminate\\Console\\Command;
use Illuminate\\Support\\Facades\\Log;
use Illuminate\\Support\\Facades\\Storage;

class PurgeExpiredReportArtifacts extends Command
{
    protected $signature = 'reports:purge-expired-artifacts';

    protected $description = 'حذف ملفات التقارير المحمية المنتهية مع الاحتفاظ بسجل التنفيذ وبياناته الوصفية';

    public function handle(): int
    {
        $purged = 0;
        $failed = 0;

        ReportRun::query()
            ->whereNotNull('artifact_path')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now('Asia/Aden'))
            ->orderBy('report_run_id')
            ->chunkById(100, function ($runs) use (&$purged, &$failed): void {
                foreach ($runs as $run) {
                    $path = $run->artifact_path;
                    if ($path !== null && ! Storage::disk('protected')->delete($path)) {
                        $failed++;
                        Log::warning('Could not delete expired report artifact.', [
                            'report_run_id' => $run->report_run_id,
                            'artifact_path' => $path,
                        ]);
                        continue;
                    }

                    $run->forceFill([
                        'status' => 'EXPIRED',
                        'artifact_path' => null,
                    ])->save();

                    $purged++;
                }
            }, 'report_run_id');

        $this->info("تم تنظيف {$purged} ملف تقرير منتهي الصلاحية، وتعذر حذف {$failed} ملف.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
