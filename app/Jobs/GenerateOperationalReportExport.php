<?php

namespace App\Jobs;

use App\Enums\OperationalReportExportStatus;
use App\Models\OperationalReportExport;
use App\Models\User;
use App\Services\OperationalReportAuthorizationService;
use App\Services\OperationalReportQueryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class GenerateOperationalReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public int $reportExportId) {}

    public function handle(
        OperationalReportAuthorizationService $authorization,
        OperationalReportQueryService $queries
    ): void {
        $export = OperationalReportExport::query()->find($this->reportExportId);
        if (! $export || $export->status !== OperationalReportExportStatus::QUEUED) {
            return;
        }

        $export->update([
            'status' => OperationalReportExportStatus::PROCESSING,
            'started_at' => now('Asia/Aden'),
        ]);

        try {
            $user = User::query()->findOrFail($export->requested_by);
            $authorization->authorize($user, $export->report_type, (int) $export->network_id, $export->pos_id ? (int) $export->pos_id : null, true);
            $filters = $export->filters ?? [];
            $filters['per_page'] = 100;
            $filters['cursor'] = null;
            $stream = fopen('php://temp', 'w+b');
            if ($stream === false) {
                throw new \RuntimeException('REPORT_EXPORT_STREAM_UNAVAILABLE');
            }
            fwrite($stream, "\\xEF\\xBB\\xBF");
            $headerWritten = false;
            $recordCount = 0;
            do {
                $page = $queries->build($user, $export->report_type, (int) $export->network_id, $export->pos_id ? (int) $export->pos_id : null, $filters);
                if (is_array($page)) {
                    $items = collect($page);
                    $nextCursor = null;
                } else {
                    $items = collect($page->items());
                    $nextCursor = $page->nextCursor()?->encode();
                }
                foreach ($items as $row) {
                    $recordCount++;
                    if ($recordCount > 50000) {
                        throw new \RuntimeException('REPORT_EXPORT_LIMIT_EXCEEDED');
                    }
                    $values = $row instanceof \Illuminate\Database\Eloquent\Model ? $row->toArray() : (array) $row;
                    $flat = [];
                    array_walk_recursive($values, function ($value, $key) use (&$flat): void {
                        if (is_scalar($value) || $value === null) {
                            $flat[(string) $key] = $value;
                        }
                    });
                    if (! $headerWritten) {
                        fputcsv($stream, array_keys($flat));
                        $headerWritten = true;
                    }
                    fputcsv($stream, array_values($flat));
                }
                $filters['cursor'] = $nextCursor;
            } while ($nextCursor !== null);
            
            rewind($stream);
            $csv = stream_get_contents($stream);
            fclose($stream);
            if ($csv === false) {
                throw new \RuntimeException('REPORT_EXPORT_STREAM_READ_FAILED');
            }

            $path = 'operational-reports/'.$export->network_id.'/'.$export->export_uuid.'.csv';
            Storage::disk('protected')->put($path, $csv);
            $export->update([
                'status' => OperationalReportExportStatus::READY,
                'artifact_path' => $path,
                'artifact_sha256' => hash('sha256', $csv),
                'artifact_size' => strlen($csv),
                'record_count' => $recordCount,
                'completed_at' => now('Asia/Aden'),
            ]);
        } catch (Throwable $exception) {
            $export->update([
                'status' => OperationalReportExportStatus::FAILED,
                'failure_code' => preg_match('/^[A-Z0-9_]+$/', $exception->getMessage())
                    ? $exception->getMessage()
                    : 'REPORT_EXPORT_FAILED',
                'completed_at' => now('Asia/Aden'),
            ]);
            throw $exception;
        }
    }
}
