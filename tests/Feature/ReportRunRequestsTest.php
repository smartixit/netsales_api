<?php

namespace Tests\Feature;

use App\Http\Requests\API\V1\Reporting\ReportRunIndexRequest;
use App\Http\Requests\API\V1\Reporting\StoreReportRunRequest;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReportRunRequestsTest extends TestCase
{
    public function test_store_request_requires_a_report_key(): void
    {
        Route::post('/_test/report-runs', function (StoreReportRunRequest $request) {
            return response()->json($request->validated());
        });

        $this->postJson('/_test/report-runs', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['report_key']);
    }

    public function test_store_request_accepts_a_report_key_and_nested_filters(): void
    {
        Route::post('/_test/report-runs', function (StoreReportRunRequest $request) {
            return response()->json($request->validated());
        });

        $this->postJson('/_test/report-runs', [
            'report_key' => 'sales-invoices',
            'filters' => ['from' => '2026-11-01'],
        ])
            ->assertOk()
            ->assertExactJson([
                'report_key' => 'sales-invoices',
                'filters' => ['from' => '2026-11-01'],
            ]);
    }

    public function test_index_request_rejects_a_page_size_above_the_service_limit(): void
    {
        Route::get('/_test/report-runs', function (ReportRunIndexRequest $request) {
            return response()->json($request->validated());
        });

        $this->getJson('/_test/report-runs?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }
}
