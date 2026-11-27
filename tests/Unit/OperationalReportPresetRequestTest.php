<?php

namespace Tests\Unit;

use App\Enums\OperationalReportType;
use App\Http\Requests\API\V1\Reports\StoreOperationalReportPresetRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class OperationalReportPresetRequestTest extends TestCase
{
    public function test_removed_repayments_report_type_is_rejected(): void
    {
        $validator = Validator::make(
            $this->validPayload('repayments'),
            (new StoreOperationalReportPresetRequest())->rules()
        );

        $this->assertArrayHasKey('report_type', $validator->errors()->messages());
    }

    public function test_every_registered_operational_report_type_is_accepted(): void
    {
        foreach (OperationalReportType::cases() as $type) {
            $validator = Validator::make(
                $this->validPayload($type->value),
                (new StoreOperationalReportPresetRequest())->rules()
            );

            $this->assertFalse(
                $validator->fails(),
                sprintf('Expected report type [%s] to be valid: %s', $type->value, $validator->errors()->toJson())
            );
        }
    }

    /** @return array<string, mixed> */
    private function validPayload(string $reportType): array
    {
        return [
            'report_type' => $reportType,
            'network_id' => 1,
            'name' => 'Monthly report',
            'report_schema_version' => 1,
            'criteria' => ['filters' => ['probe' => ['type' => 'text', 'value' => 'valid']]],
            'presentation' => [
                'include_metrics' => false,
                'include_charts' => false,
            ],
        ];
    }
}
