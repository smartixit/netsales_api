<?php

namespace App\Jobs;

use App\Enums\OperationalReportExportStatus;
use App\Models\OperationalReportExport;
use App\Models\User;
use App\Services\OperationalReportAuthorizationService;
use App\Services\Reports\OperationalReportProviderRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class GenerateOperationalReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;

    public function __construct(public int $reportExportId) {}

    public function handle(
        OperationalReportAuthorizationService $authorization,
        OperationalReportProviderRegistry $providers
    ): void {
        $claim = DB::transaction(function (): string {
            $export = OperationalReportExport::query()->lockForUpdate()->find($this->reportExportId);
            if (! $export) {
                return 'missing';
            }

            if ($export->status === OperationalReportExportStatus::PROCESSING) {
                $leaseExpired = $export->started_at === null
                    || $export->started_at->lte(now('Asia/Aden')->subSeconds($this->timeout + 10));
                if ($this->attempts() <= 1 || ! $leaseExpired) {
                    return 'release';
                }
            } elseif ($export->status !== OperationalReportExportStatus::QUEUED) {
                return 'skip';
            }

            $export->update([
                'status' => OperationalReportExportStatus::PROCESSING,
                'started_at' => now('Asia/Aden'),
                'completed_at' => null,
                'failure_code' => null,
            ]);

            return 'claimed';
        }, 3);

        if ($claim === 'release') {
            $this->release(110);
            return;
        }
        if ($claim !== 'claimed') {
            return;
        }

        $export = OperationalReportExport::query()->find($this->reportExportId);
        if (! $export) {
            return;
        }

        try {
            $user = User::query()->findOrFail($export->requested_by);
            $type = $export->report_type;
            $networkId = (int) $export->network_id;
            $posId = $export->pos_id ? (int) $export->pos_id : null;
            $authorization->authorize($user, $type, $networkId, $posId, true);

            $provider = $providers->get($type);
            $filters = $export->filters ?? [];
            $filters['per_page'] = 100;
            $filters['cursor'] = null;

            $rowStream = fopen('php://temp', 'w+b');
            if ($rowStream === false) {
                throw new \RuntimeException('REPORT_EXPORT_STREAM_UNAVAILABLE');
            }

            $recordCount = 0;
            do {
                $page = $provider->build($user, $networkId, $posId, $filters);
                foreach ($page->items() as $row) {
                    $recordCount++;
                    if ($recordCount > 50000) {
                        throw new \RuntimeException('REPORT_EXPORT_LIMIT_EXCEEDED');
                    }

                    $serialized = $provider->serializeRow($row);
                    $encoded = json_encode(
                        $serialized,
                        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
                    );
                    if (fwrite($rowStream, $encoded."\n") === false) {
                        throw new \RuntimeException('REPORT_EXPORT_STREAM_WRITE_FAILED');
                    }
                }
                $filters['cursor'] = $page->nextCursor()?->encode();
            } while ($filters['cursor'] !== null);

            $stream = fopen('php://temp/maxmemory:2097152', 'w+b');
            if ($stream === false) {
                fclose($rowStream);
                throw new \RuntimeException('REPORT_EXPORT_STREAM_UNAVAILABLE');
            }

            if (fwrite($stream, "\xEF\xBB\xBF") === false) {
                fclose($rowStream);
                fclose($stream);
                throw new \RuntimeException('REPORT_EXPORT_STREAM_WRITE_FAILED');
            }

            $columns = $provider->exportColumns();
            if (fputcsv($stream, array_values($columns)) === false) {
                fclose($rowStream);
                fclose($stream);
                throw new \RuntimeException('REPORT_EXPORT_CSV_HEADER_FAILED');
            }

            rewind($rowStream);
            while (($line = fgets($rowStream)) !== false) {
                $values = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                $csvRow = [];
                foreach (array_keys($columns) as $field) {
                    $csvRow[] = $this->safeCsvValue($values[$field] ?? null);
                }
                if (fputcsv($stream, $csvRow) === false) {
                    fclose($rowStream);
                    fclose($stream);
                    throw new \RuntimeException('REPORT_EXPORT_CSV_ROW_FAILED');
                }
            }
            fclose($rowStream);

            rewind($stream);
            $hashContext = hash_init('sha256');
            $artifactSize = hash_update_stream($hashContext, $stream);
            if ($artifactSize === false) {
                fclose($stream);
                throw new \RuntimeException('REPORT_EXPORT_STREAM_READ_FAILED');
            }
            $artifactHash = hash_final($hashContext);

            rewind($stream);
            $path = 'operational-reports/'.$networkId.'/'.$export->export_uuid.'.csv';
            $stored = Storage::disk('protected')->writeStream($path, $stream);
            fclose($stream);
            if (! $stored) {
                throw new \RuntimeException('REPORT_EXPORT_STORAGE_WRITE_FAILED');
            }

            $export->update([
                'status' => OperationalReportExportStatus::READY,
                'artifact_path' => $path,
                'artifact_sha256' => $artifactHash,
                'artifact_size' => $artifactSize,
                'record_count' => $recordCount,
                'completed_at' => now('Asia/Aden'),
            ]);
        } catch (Throwable $exception) {
            $willRetry = $this->attempts() < $this->tries;
            $export->update([
                'status' => $willRetry ? OperationalReportExportStatus::QUEUED : OperationalReportExportStatus::FAILED,
                'failure_code' => $willRetry ? null : $this->failureCode($exception),
                'completed_at' => $willRetry ? null : now('Asia/Aden'),
            ]);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $cutoff = now('Asia/Aden')->subSeconds(max(1, $this->timeout - 5));
        OperationalReportExport::query()
            ->where('report_export_id', $this->reportExportId)
            ->where('status', OperationalReportExportStatus::PROCESSING->value)
            ->where(fn ($query) => $query->whereNull('started_at')->orWhere('started_at', '<=', $cutoff))
            ->update([
                'status' => OperationalReportExportStatus::FAILED->value,
                'failure_code' => $this->failureCode($exception),
                'completed_at' => now('Asia/Aden'),
            ]);
    }

    private function failureCode(?Throwable $exception): string
    {
        $message = $exception?->getMessage() ?? 'REPORT_EXPORT_FAILED';

        return preg_match('/^[A-Z0-9_]+$/', $message) === 1 ? $message : 'REPORT_EXPORT_FAILED';
    }

    private function safeCsvValue(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || is_numeric($value)) {
            return $value;
        }

        return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
