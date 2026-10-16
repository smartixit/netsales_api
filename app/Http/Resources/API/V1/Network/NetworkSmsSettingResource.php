<?php

namespace App\Http\Resources\API\V1\Network;

use App\Models\NetworkSmsSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NetworkSmsSetting
 */
class NetworkSmsSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'network_id' => $this->network_id,
            'is_enabled' => (bool) $this->is_enabled,
            'notify_receipt' => (bool) $this->notify_receipt,
            'notify_payment' => (bool) $this->notify_payment,
            'notify_pos_withdraw' => (bool) $this->notify_pos_withdraw,
            'notify_expense' => (bool) $this->notify_expense,
            'notify_sale_invoice' => (bool) $this->notify_sale_invoice,
            'notify_voucher_request' => (bool) $this->notify_voucher_request,
            'notify_low_balance' => (bool) $this->notify_low_balance,
            'notify_daily_summary' => (bool) $this->notify_daily_summary,
            'notify_monthly_summary' => (bool) $this->notify_monthly_summary,
            'notify_subscription_expiry' => (bool) $this->notify_subscription_expiry,
            'use_custom_gateway' => (bool) $this->use_custom_gateway,
            'custom_gateway' => [
                'server_type' => $this->server_type ?? 'cloud',
                'base_url' => $this->base_url ?? 'https://api.sms-gate.app',
                'auth_type' => $this->auth_type ?? 'bearer',
                'api_token' => $this->api_token ? '••••••••' : null,
                'has_api_token' => ! empty($this->api_token),
                'username' => $this->username,
                'has_password' => ! empty($this->password),
                'device_id' => $this->device_id,
                'sim_number' => (int) ($this->sim_number ?? 1),
                'skip_phone_validation' => (bool) ($this->skip_phone_validation ?? false),
                'device_active_within' => (int) ($this->device_active_within ?? 0),
            ],
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
