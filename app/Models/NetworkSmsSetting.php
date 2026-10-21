<?php

namespace App\Models;

use App\Enums\SmsMessageType;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $network_id
 * @property bool $is_enabled
 * @property bool $notify_receipt
 * @property bool $notify_payment
 * @property bool $notify_pos_withdraw
 * @property bool $notify_expense
 * @property bool $notify_sale_invoice
 * @property bool $notify_voucher_request
 * @property bool $notify_low_balance
 * @property bool $notify_daily_summary
 * @property bool $notify_monthly_summary
 * @property bool $notify_subscription_expiry
 * @property bool $use_custom_gateway
 * @property string $server_type
 * @property string $base_url
 * @property string $auth_type
 * @property string|null $api_token
 * @property string|null $refresh_token
 * @property \Carbon\Carbon|null $token_expires_at
 * @property string|null $username
 * @property string|null $password
 * @property string|null $device_id
 * @property int $sim_number
 * @property bool $skip_phone_validation
 * @property int $device_active_within
 */
class NetworkSmsSetting extends Model
{
    use ExactDateSerialization;

    protected $table = 'network_sms_settings';

    protected $fillable = [
        'network_id',
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
        'token_expires_at',
        'username',
        'password',
        'device_id',
        'sim_number',
        'skip_phone_validation',
        'device_active_within',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'notify_receipt' => 'boolean',
            'notify_payment' => 'boolean',
            'notify_pos_withdraw' => 'boolean',
            'notify_expense' => 'boolean',
            'notify_sale_invoice' => 'boolean',
            'notify_voucher_request' => 'boolean',
            'notify_low_balance' => 'boolean',
            'notify_daily_summary' => 'boolean',
            'notify_monthly_summary' => 'boolean',
            'notify_subscription_expiry' => 'boolean',
            'use_custom_gateway' => 'boolean',
            'sim_number' => 'integer',
            'skip_phone_validation' => 'boolean',
            'device_active_within' => 'integer',
            'token_expires_at' => 'datetime',
            'password' => 'encrypted',
        ];
    }

    protected $hidden = [
        'password',
    ];

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class, 'network_id', 'network_id');
    }

    /**
     * التحقق مما إذا كان نوع الرسالة مسموحاً للشبكة وفق المفتاح العام ومفتاح النوع.
     */
    public function isMessageTypeEnabled(?string $type): bool
    {
        // إذا كان المفتاح العام للشبكة معطلاً، تُحجب كافة الرسائل التجارية
        if (! $this->is_enabled) {
            return false;
        }

        if (empty($type)) {
            return false;
        }

        $enum = SmsMessageType::tryFrom($type);
        if (! $enum) {
            return false;
        }

        $col = $enum->settingColumn();
        if ($col === null) {
            return false;
        }

        return (bool) $this->{$col};
    }

    /**
     * تحويل إعدادات بوابة الشبكة إلى المصفوفة المعتمدة لدى SMSGateService.
     */
    public function toGatewayConfigArray(): array
    {
        return [
            'server_type' => $this->server_type ?? 'cloud',
            'base_url' => $this->base_url ?? 'https://api.sms-gate.app',
            'auth_type' => $this->auth_type ?? 'bearer',
            'api_token' => $this->api_token,
            'refresh_token' => $this->refresh_token,
            'token_expires_at' => $this->token_expires_at?->toDateTimeString(),
            'username' => $this->username,
            'password' => $this->password,
            'device_id' => $this->device_id,
            'sim_number' => (int) ($this->sim_number ?? 1),
            'skip_phone_validation' => (bool) ($this->skip_phone_validation ?? false),
            'device_active_within' => (int) ($this->device_active_within ?? 0),
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_enabled', false);
    }

    public function scopeForNetwork(Builder $query, int $networkId): Builder
    {
        return $query->where('network_id', $networkId);
    }
}
