<?php

namespace App\Repositories;

use App\Interfaces\NetworkSmsSettingRepositoryInterface;
use App\Models\NetworkSmsSetting;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع إدارة إعدادات وبوابات الرسائل للشبكات (Network SMS Setting Repository).
 *
 * @property NetworkSmsSetting $model
 */
class NetworkSmsSettingRepository extends BaseRepository implements NetworkSmsSettingRepositoryInterface
{
    public function __construct(NetworkSmsSetting $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب قائمة إعدادات الرسائل مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = []): Collection|CursorPaginator
    {
        $defaultRelations = ['network'];
        $withRelations = ! empty($relations) ? $relations : $defaultRelations;
        $query = $this->model->newQuery()->with($withRelations);

        if (! empty($filters['network_id'])) {
            $query->where('network_id', (int) $filters['network_id']);
        }

        if (isset($filters['is_enabled'])) {
            $query->where('is_enabled', (bool) $filters['is_enabled']);
        }

        if (isset($filters['use_custom_gateway'])) {
            $query->where('use_custom_gateway', (bool) $filters['use_custom_gateway']);
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('id', 'desc')->get();
        }

        return $query->orderBy('id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * جلب إعدادات الرسائل لشبكة معينة أو إنشاء الافتراضية
     */
    public function getOrCreateForNetwork(int $networkId): NetworkSmsSetting
    {
        /** @var NetworkSmsSetting $setting */
        $setting = $this->model->newQuery()->firstOrCreate(
            ['network_id' => $networkId],
            [
                'is_enabled' => true,
                'notify_receipt' => true,
                'notify_payment' => true,
                'notify_pos_withdraw' => true,
                'notify_expense' => true,
                'notify_sale_invoice' => true,
                'notify_voucher_request' => true,
                'notify_low_balance' => true,
                'notify_daily_summary' => true,
                'notify_monthly_summary' => true,
                'notify_subscription_expiry' => true,
                'use_custom_gateway' => false,
                'server_type' => 'cloud',
                'base_url' => 'https://api.sms-gate.app',
                'auth_type' => 'bearer',
                'sim_number' => 1,
                'skip_phone_validation' => true,
                'device_active_within' => 0,
            ]
        );

        return $setting;
    }

    /**
     * تحديث أو إنشاء إعدادات الرسائل لشبكة معينة
     */
    public function updateOrCreateForNetwork(int $networkId, array $data): NetworkSmsSetting
    {
        /** @var NetworkSmsSetting $setting */
        $setting = $this->model->newQuery()->updateOrCreate(
            ['network_id' => $networkId],
            $data
        );

        return $setting;
    }
}
