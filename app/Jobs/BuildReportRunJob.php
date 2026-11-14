<?php

namespace App\Jobs;

use App\Models\ReportRun;
use App\Services\Reporting\ReportExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BuildReportRunJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(
        public readonly int $reportRunId
    ) {}

    /**
     * @return array<int, WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("report-run:{$this->reportRunId}"))
                ->releaseAfter(15)
                ->expireAfter(330),
        ];
    }

    public function handle(ReportExecutionService $executor): void
    {
        $run = ReportRun::query()->findOrFail($this->reportRunId);
        if ($run->status === 'COMPLETED' || $run->status === 'EXPIRED') {
            return;
        }

        $run->forceFill([
            'status' => 'RUNNING',
            'started_at' => now('Asia/Aden'),
            'error_code' => null,
            'error_message' => null,
        ])->save();

        $result = $executor->execute($run);
        $json = json_encode(
            $result['payload'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );

        $path = sprintf(
            'report-artifacts/%s/%s/%s.json',
            strtolower($run->scope_type),
            $run->network_id === null ? 'platform' : (string) $run->network_id,
            $run->report_run_uuid
        );

        $stored = Storage::disk('protected')->put($path, $json);
        if (! $stored) {
            throw new \RuntimeException('Failed to store protected report artifact.');
        }

        $run->forceFill([
            'status' => 'COMPLETED',
            'artifact_format' => 'JSON',
            'artifact_path' => $path,
            'artifact_sha256' => hash('sha256', $json),
            'artifact_bytes' => strlen($json),
            'records_count' => (int) $result['records_count'],
            'is_truncated' => (bool) $result['is_truncated'],
            'result_metadata' => $result['payload']['metadata'] ?? [],
            'completed_at' => now('Asia/Aden'),
            'expires_at' => now('Asia/Aden')->addDays(30),
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        ReportRun::query()
            ->whereKey($this->reportRunId)
            ->whereNotIn('status', ['COMPLETED', 'EXPIRED'])
            ->update([
                'status' => 'FAILED',
                'error_code' => class_basename($exception),
                'error_message' => 'تعذر إنشاء التقرير. حاول إعادة تشغيله بعد مراجعة المرشحات.',
                'completed_at' => now('Asia/Aden'),
                'updated_at' => now('Asia/Aden'),
            ]);

        Log::error('Report run failed.', [
            'report_run_id' => $this->reportRunId,
            'exception' => $exception::class,
        ]);
    }
}
