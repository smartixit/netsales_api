<?php

namespace App\Http\Requests\API\V1\Network;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNetworkSmsSettingRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'base_url' => is_string($this->base_url) ? trim($this->base_url) : $this->base_url,
            'api_token' => is_string($this->api_token) ? trim($this->api_token) : $this->api_token,
            'device_id' => is_string($this->device_id) ? trim($this->device_id) : $this->device_id,
            'username' => is_string($this->username) ? trim($this->username) : $this->username,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'is_enabled' => ['sometimes', 'boolean'],
            'notify_receipt' => ['sometimes', 'boolean'],
            'notify_payment' => ['sometimes', 'boolean'],
            'notify_pos_withdraw' => ['sometimes', 'boolean'],
            'notify_expense' => ['sometimes', 'boolean'],
            'notify_sale_invoice' => ['sometimes', 'boolean'],
            'notify_voucher_request' => ['sometimes', 'boolean'],
            'notify_low_balance' => ['sometimes', 'boolean'],
            'notify_daily_summary' => ['sometimes', 'boolean'],
            'notify_monthly_summary' => ['sometimes', 'boolean'],
            'notify_subscription_expiry' => ['sometimes', 'boolean'],
            'use_custom_gateway' => ['sometimes', 'boolean'],
            'server_type' => ['sometimes', 'string', 'in:cloud,local,private'],
            'base_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'auth_type' => ['sometimes', 'string', 'in:bearer,basic'],
            'api_token' => ['sometimes', 'nullable', 'string'],
            'refresh_token' => ['sometimes', 'nullable', 'string'],
            'username' => ['sometimes', 'nullable', 'string', 'max:100'],
            'password' => ['sometimes', 'nullable', 'string'],
            'device_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sim_number' => ['sometimes', 'integer', 'min:1', 'max:3'],
            'skip_phone_validation' => ['sometimes', 'boolean'],
            'device_active_within' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * استخراج بيانات الإعدادات المرسلة مع استبعاد القيم غير المحددة
     */
    public function getSettingsData(): array
    {
        $fields = [
            'is_enabled',
            'notify_receipt',
            'notify_payment',
            'notify_pos_withdraw',
            'notify_expense',
            'notify_sale_invoice',
            'notify_voucher_request',
            'notify_low_balance',
            'notify_daily_summary',
            'notify_monthly_summary',
            'notify_subscription_expiry',
            'use_custom_gateway',
            'server_type',
            'base_url',
            'auth_type',
            'api_token',
            'refresh_token',
            'username',
            'password',
            'device_id',
            'sim_number',
            'skip_phone_validation',
            'device_active_within',
        ];

        $data = [];
        foreach ($fields as $field) {
            if ($this->has($field)) {
                $data[$field] = $this->input($field);
            }
        }

        return $data;
    }
}
