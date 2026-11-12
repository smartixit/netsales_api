<?php

namespace App\Http\Requests\API\V1\Reports;

use App\Enums\OperationalReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOperationalReportExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $type = OperationalReportType::tryFrom((string) $this->input('report_type'));
            if ($type === null) {
                return;
            }

            $allowedStatuses = match ($type) {
                OperationalReportType::SALES => ['PAID', 'PARTIALLY_PAID', 'UNPAID'],
                OperationalReportType::DEBT_AGING => ['PARTIALLY_PAID', 'UNPAID'],
                OperationalReportType::NETWORK_WALLET,
                OperationalReportType::POS_WALLET => ['DEPOSIT', 'WITHDRAW'],
                OperationalReportType::REPAYMENTS => ['COMPLETED', 'REVERSED'],
                OperationalReportType::REQUESTS => ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'],
                OperationalReportType::EXPENSES => [],
            };

            $saleType = $this->input('sale_type');
            if (
                $saleType !== null &&
                ! in_array($type, [
                    OperationalReportType::SALES,
                    OperationalReportType::REQUESTS,
                ], true)
            ) {
                $validator->errors()->add(
                    'sale_type',
                    'نوع البيع لا ينطبق على نوع التقرير المطلوب.'
                );
            }

            $status = $this->input('status');
            if ($status !== null && ! in_array($status, $allowedStatuses, true)) {
                $validator->errors()->add(
                    'status',
                    'حالة التقرير المحددة غير صالحة لنوع التقرير المطلوب.'
                );
            }

            if (
                in_array($type, [
                    OperationalReportType::EXPENSES,
                    OperationalReportType::NETWORK_WALLET,
                ], true) &&
                $this->filled('pos_id')
            ) {
                $validator->errors()->add(
                    'pos_id',
                    'هذا التقرير على مستوى الشبكة ولا يقبل تصفية نقطة البيع.'
                );
            }

            if (
                $type === OperationalReportType::POS_WALLET &&
                ! $this->filled('pos_id')
            ) {
                $validator->errors()->add(
                    'pos_id',
                    'تقرير محفظة نقطة البيع يتطلب تحديد نقطة البيع.'
                );
            }
        });
    }

    public function rules(): array
    {
        return [
            'report_type' => ['required', Rule::enum(OperationalReportType::class)],
            'network_id' => ['required', 'integer', 'min:1'],
            'pos_id' => ['nullable', 'integer', 'min:1'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'sale_type' => ['nullable', 'in:CASH,CREDIT'],
            'status' => ['nullable', 'string', 'max:32'],
            'search' => ['nullable', 'string', 'max:120'],
        ];
    }
}
