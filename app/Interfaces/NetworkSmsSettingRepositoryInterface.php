<?php

namespace App\Interfaces;

use App\Models\NetworkSmsSetting;

interface NetworkSmsSettingRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب إعدادات الرسائل لشبكة معينة أو إنشاء الافتراضية
     */
    public function getOrCreateForNetwork(int $networkId): NetworkSmsSetting;

    /**
     * تحديث أو إنشاء إعدادات الرسائل لشبكة معينة
     */
    public function updateOrCreateForNetwork(int $networkId, array $data): NetworkSmsSetting;
}
