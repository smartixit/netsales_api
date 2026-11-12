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
        if (
            ! $export ||
            (
                $export->status !== OperationalReportExportStatus::QUEUED &&
                ! (
                    $export->status === OperationalReportExportStatus::PROCESSING &&
                    $this->attempts() > 1
                )
            )
        ) {
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
            $rowStream = fopen('php://temp', 'w+b');
            if ($rowStream === false) {
                throw new \RuntimeException('REPORT_EXPORT_STREAM_UNAVAILABLE');
            }

            $columns = [];
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

                    $values = $row instanceof \Illuminate\Database\Eloquent\Model
                        ? $row->toArray()
                        : (array) $row;
                    $flat = $this->flattenCsvValues($values);
                    foreach (array_keys($flat) as $column) {
                        $columns[$column] = true;
                    }

                    $encoded = json_encode(
                        $flat,
                        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
                    );
                    if (fwrite($rowStream, $encoded . "\n") === false) {
                        throw new \RuntimeException('REPORT_EXPORT_STREAM_WRITE_FAILED');
                    }
                }

                $filters['cursor'] = $nextCursor;
            } while ($nextCursor !== null);

            $stream = fopen('php://temp', 'w+b');
            if ($stream === false) {
                fclose($rowStream);
                throw new \RuntimeException('REPORT_EXPORT_STREAM_UNAVAILABLE');
            }

            // UTF-8 BOM allows spreadsheet applications to recognize Arabic text.
            fwrite($stream, "\xEF\xBB\xBF");
            if ($recordCount === 0) {
                $columns = array_fill_keys(match ($export->report_type) {
                    \App\Enums\OperationalReportType::SALES => [
                        'invoice_id', 'invoice_uuid', 'invoice_date', 'sale_type',
                        'payment_status', 'invoice_total_amount', 'paid_amount',
                        'remaining_amount', 'network_id', 'pos_id',
                    ],
                    \App\Enums\OperationalReportType::EXPENSES => [
                        'expense_id', 'expense_uuid', 'expense_date',
                        'expense_total_amount', 'network_id', 'created_by',
                    ],
                    \App\Enums\OperationalReportType::NETWORK_WALLET => [
                        'log_id', 'log_date', 'log_operation', 'log_amount',
                        'log_statement', 'network_wallet_id',
                    ],
                    \App\Enums\OperationalReportType::POS_WALLET => [
                        'log_id', 'log_date', 'log_operation_type', 'log_amount',
                        'log_statement', 'pos_wallet_id',
                    ],
                    \App\Enums\OperationalReportType::REPAYMENTS => [
                        'repayment_id', 'repayment_uuid', 'repayment_date',
                        'amount', 'status', 'reference_number', 'notes',
                    ],
                    \App\Enums\OperationalReportType::DEBT_AGING => [
                        'invoice_id', 'invoice_uuid', 'invoice_date',
                        'invoice_total_amount', 'paid_amount', 'remaining_amount',
                        'payment_status', 'network_id', 'pos_id',
                    ],
                    \App\Enums\OperationalReportType::REQUESTS => [
                        'request_id', 'request_uuid', 'request_date', 'sale_type',
                        'status', 'request_total_amount', 'request_note',
                        'network_id', 'pos_id',
                    ],
                }, true);
            }
            $columnNames = array_keys($columns);
            fputcsv($stream, $columnNames);
            if ($recordCount > 0) {
                rewind($rowStream);

                while (($line = fgets($rowStream)) !== false) {
                    $flat = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    $csvRow = [];
                    foreach ($columnNames as $column) {
                        $csvRow[] = $this->safeCsvValue($flat[$column] ?? null);
                    }
                    fputcsv($stream, $csvRow);
                }
            }

            fclose($rowStream);
            rewind($stream);
            $csv = stream_get_contents($stream);
            fclose($stream);
            if ($csv === false) {
                throw new \RuntimeException('REPORT_EXPORT_STREAM_READ_FAILED');
            }

            $path = 'operational-reports/'.$export->network_id.'/'.$export->export_uuid.'.csv';
            if (! Storage::disk('protected')->put($path, $csv)) {
                throw new \RuntimeException('REPORT_EXPORT_STORAGE_WRITE_FAILED');
            }
            $export->update([
                'status' => OperationalReportExportStatus::READY,
                'artifact_path' => $path,
                'artifact_sha256' => hash('sha256', $csv),
                'artifact_size' => strlen($csv),
                'record_count' => $recordCount,
                'completed_at' => now('Asia/Aden'),
            ]);
        } catch (Throwable $exception) {
            $willRetry = $this->attempts() < $this->tries;
            $export->update([
                'status' => $willRetry
                    ? OperationalReportExportStatus::QUEUED
                    : OperationalReportExportStatus::FAILED,
                'failure_code' => $willRetry
                    ? null
                    : (preg_match('/^[A-Z0-9_]+$/', $exception->getMessage())
                        ? $exception->getMessage()
                        : 'REPORT_EXPORT_FAILED'),
                'completed_at' => $willRetry ? null : now('Asia/Aden'),
            ]);
            throw $exception;
        }
    }

    /**
     * Flattens nested model attributes using full paths so identically named
     * fields from different relationships cannot overwrite one another.
     */
    private function flattenCsvValues(array $values, string $prefix = ''): array
    {
        $flat = [];

        foreach ($values as $key => $value) {
            $column = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            } elseif ($value instanceof \DateTimeInterface) {
                $value = $value->format(DATE_ATOM);
            }

            if (is_array($value)) {
                $flat += $this->flattenCsvValues($value, $column);
            } elseif (is_scalar($value) || $value === null) {
                $flat[$column] = $value;
            }
        }

        return $flat;
    }

    /**
     * Neutralizes spreadsheet formulas without changing numeric values.
     */
    private function safeCsvValue(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || is_numeric($value)) {
            return $value;
        }

        return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1
            ? "'".$value
            : $value;
    }
}
