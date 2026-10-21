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
     * البحث عن إعدادات الرسائل لشبكة معينة عبر معرف الشبكة
     */
    public function findByNetworkId(int $networkId): ?NetworkSmsSetting;

    /**
     * تحديث أو إنشاء إعدادات الرسائل لشبكة معينة
     */
    public function updateOrCreateForNetwork(int $networkId, array $data): NetworkSmsSetting;
}
