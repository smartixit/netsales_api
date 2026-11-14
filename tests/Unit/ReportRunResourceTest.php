<?php

namespace Tests\Unit;

use App\Http\Resources\API\V1\Reporting\ReportRunResource;
use App\Services\Reporting\ReportRunData;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class ReportRunResourceTest extends TestCase
{
    public function test_resource_serializes_the_reporting_read_model_to_the_stable_api_contract(): void
    {
        $data = new ReportRunData(
            reportRunId: 42,
            reportRunUuid: '8ce3c1ba-43a7-4d54-9a2f-301ea2eaa0ef',
            scopeType: 'NETWORK',
            networkId: 17,
            reportKey: 'sales-invoices',
            reportTitle: 'فواتير المبيعات التشغيلية',
            definitionVersion: '1.0.0',
            status: 'COMPLETED',
            filters: ['from' => '2026-11-01'],
            artifact: [
                'format' => 'JSON',
                'bytes' => 4096,
                'sha256' => 'a'.str_repeat('b', 63),
                'records_count' => 12,
                'is_truncated' => false,
                'expires_at' => '2026-12-14 12:00:00',
                'download_url' => 'https://example.test/signed-report',
            ],
            resultMetadata: ['currency_aggregation' => 'SEPARATE_PER_RECORD'],
            error: null,
            queuedAt: '2026-11-14 12:00:00',
            startedAt: '2026-11-14 12:00:01',
            completedAt: '2026-11-14 12:00:02',
        );

        $payload = ReportRunResource::make($data)->resolve(Request::create('/'));

        self::assertSame([
            'report_run_id' => 42,
            'report_run_uuid' => '8ce3c1ba-43a7-4d54-9a2f-301ea2eaa0ef',
            'scope_type' => 'NETWORK',
            'network_id' => 17,
            'report_key' => 'sales-invoices',
            'report_title' => 'فواتير المبيعات التشغيلية',
            'definition_version' => '1.0.0',
            'status' => 'COMPLETED',
            'filters' => ['from' => '2026-11-01'],
            'artifact' => [
                'format' => 'JSON',
                'bytes' => 4096,
                'sha256' => 'a'.str_repeat('b', 63),
                'records_count' => 12,
                'is_truncated' => false,
                'expires_at' => '2026-12-14 12:00:00',
                'download_url' => 'https://example.test/signed-report',
            ],
            'result_metadata' => ['currency_aggregation' => 'SEPARATE_PER_RECORD'],
            'error' => null,
            'queued_at' => '2026-11-14 12:00:00',
            'started_at' => '2026-11-14 12:00:01',
            'completed_at' => '2026-11-14 12:00:02',
        ], $payload);
    }
}
