<?php

namespace App\Services;

use App\Http\Requests\API\V1\Network\TestNetworkSmsRequest;
use App\Http\Requests\API\V1\Network\UpdateNetworkSmsSettingRequest;
use App\Interfaces\NetworkRepositoryInterface;
use App\Interfaces\NetworkSmsSettingRepositoryInterface;
use App\Interfaces\SystemSettingRepositoryInterface;
use App\Models\Network;
use App\Models\NetworkSmsSetting;
use App\Services\Support\SMSGateService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة وتخصيص إعدادات وبوابات الرسائل للشبكات (Network SMS Setting Service).
 *
 * مستنسخة ومطورة بدقة هندسية عالية من مشروع Qayd ومكيفة لمشروع NetSales:
 * 1. المفتاح العام للنظام (Platform Master Switch) الذي يطغى على كافة الشبكات.
 * 2. التحكم الدقيق بأنواع الرسائل العشر الخاصة بنظام مبيعات NetSales.
 * 3. حماية بيانات الاعتماد الحساسة (عدم استبدال التوكنات أو كلمة المرور إلا عند إدخال قيم جديدة فعلية).
 * 4. حل وتحديد البوابة الفعالة (Effective Gateway) بدقة مع دعم Basic Auth و Bearer.
 * 5. اختبار الاتصال الفوري ببوابة الشبكة وتأكيد وصول الرسائل.
 */
class NetworkSmsSettingService
{
    public function __construct(
        protected NetworkSmsSettingRepositoryInterface $smsSettingRepository,
        protected NetworkRepositoryInterface $networkRepository,
        protected SystemSettingRepositoryInterface $systemSettingRepository,
        protected SMSGateService $smsGateService
    ) {}

    /**
     * جلب حالة خدمة الرسائل: المفتاح العام وملخص كل شبكة
     */
    public function getOverview(): array
    {
        $globalSetting = $this->systemSettingRepository->findByKey('sms.enabled');
        $globalEnabled = $globalSetting !== null
            ? filter_var($globalSetting->setting_value, FILTER_VALIDATE_BOOLEAN)
            : true;

        $networks = $this->networkRepository->getFiltered(['all' => true], ['smsSetting']);

        $networksData = $networks->map(function (Network $network) use ($globalEnabled) {
            /** @var NetworkSmsSetting|null $setting */
            $setting = $network->smsSetting;
            $isEnabled = $setting !== null ? (bool) $setting->is_enabled : true;
            $effective = $globalEnabled && $isEnabled;

            $types = [
                'receipt' => $setting ? (bool) $setting->notify_receipt : true,
                'payment' => $setting ? (bool) $setting->notify_payment : true,
                'pos_withdraw' => $setting ? (bool) $setting->notify_pos_withdraw : true,
                'expense' => $setting ? (bool) $setting->notify_expense : true,
                'sale_invoice' => $setting ? (bool) $setting->notify_sale_invoice : true,
                'voucher_request' => $setting ? (bool) $setting->notify_voucher_request : true,
                'low_balance' => $setting ? (bool) $setting->notify_low_balance : true,
                'daily_summary' => $setting ? (bool) $setting->notify_daily_summary : true,
                'monthly_summary' => $setting ? (bool) $setting->notify_monthly_summary : true,
                'subscription_expiry' => $setting ? (bool) $setting->notify_subscription_expiry : true,
            ];

            $activeCount = count(array_filter($types));

            return [
                'network_id' => $network->network_id,
                'name' => $network->network_name,
                'status' => $network->status,
                'is_enabled' => $isEnabled,
                'effective_enabled' => $effective,
                'use_custom_gateway' => $setting ? (bool) $setting->use_custom_gateway : false,
                'device_id' => $setting?->device_id,
                'active_types_count' => $activeCount,
                'total_types_count' => count($types),
                'types' => $types,
            ];
        })->all();

        return [
            'global' => [
                'enabled' => $globalEnabled,
            ],
            'networks' => $networksData,
        ];
    }

    /**
     * تحديث المفتاح العام للرسائل (سيّد مطلق يطغى على كل الشبكات)
     */
    public function updateGlobal(bool $enabled): bool
    {
        return DB::transaction(function () use ($enabled) {
            $this->systemSettingRepository->updateOrCreateByKey('sms.enabled', [
                'setting_value' => $enabled ? '1' : '0',
                'setting_category' => 'integration',
                'setting_value_type' => 'boolean',
                'setting_desc' => 'المفتاح العام لخدمة الرسائل النصية SMS لكافة الشبكات',
            ]);

            return $enabled;
        });
    }

    /**
     * جلب تفاصيل إعدادات الرسائل النصية لشبكة محددة (البوابة، الأنواع، والبوابة الفعالة)
     */
    public function getByNetworkId(int $networkId): array
    {
        $network = $this->networkRepository->findById($networkId);
        if (! $network) {
            throw ValidationException::withMessages([
                'network_id' => ['عذراً، الشبكة المحددة غير موجودة في النظام.'],
            ]);
        }

        $setting = $this->smsSettingRepository->getOrCreateForNetwork($networkId);
        [$effectiveGateway] = $this->smsGateService->resolveGatewaySettings($networkId);

        $globalSetting = $this->systemSettingRepository->findByKey('sms.enabled');
        $globalEnabled = $globalSetting !== null
            ? filter_var($globalSetting->setting_value, FILTER_VALIDATE_BOOLEAN)
            : true;

        return [
            'network_id' => $network->network_id,
            'name' => $network->network_name,
            'status' => $network->status,
            'global_sms_enabled' => $globalEnabled,
            'effective_enabled' => $globalEnabled && (bool) $setting->is_enabled,
            'is_enabled' => (bool) $setting->is_enabled,
            'notify_receipt' => (bool) $setting->notify_receipt,
            'notify_payment' => (bool) $setting->notify_payment,
            'notify_pos_withdraw' => (bool) $setting->notify_pos_withdraw,
            'notify_expense' => (bool) $setting->notify_expense,
            'notify_sale_invoice' => (bool) $setting->notify_sale_invoice,
            'notify_voucher_request' => (bool) $setting->notify_voucher_request,
            'notify_low_balance' => (bool) $setting->notify_low_balance,
            'notify_daily_summary' => (bool) $setting->notify_daily_summary,
            'notify_monthly_summary' => (bool) $setting->notify_monthly_summary,
            'notify_subscription_expiry' => (bool) $setting->notify_subscription_expiry,
            'use_custom_gateway' => (bool) $setting->use_custom_gateway,
            'custom_gateway' => [
                'server_type' => $setting->server_type ?? 'cloud',
                'base_url' => $setting->base_url ?? 'https://api.sms-gate.app',
                'auth_type' => $setting->auth_type ?? 'bearer',
                'api_token' => $setting->api_token ? '••••••••' : null,
                'has_api_token' => ! empty($setting->api_token),
                'username' => $setting->username,
                'has_password' => ! empty($setting->password),
                'device_id' => $setting->device_id,
                'sim_number' => (int) ($setting->sim_number ?? 1),
                'skip_phone_validation' => (bool) ($setting->skip_phone_validation ?? false),
                'device_active_within' => (int) ($setting->device_active_within ?? 0),
            ],
            'effective_gateway' => $effectiveGateway ? [
                'server_type' => $effectiveGateway['server_type'] ?? 'cloud',
                'base_url' => $effectiveGateway['base_url'] ?? 'https://api.sms-gate.app',
                'auth_type' => $effectiveGateway['auth_type'] ?? 'bearer',
                'device_id' => $effectiveGateway['device_id'] ?? null,
                'sim_number' => (int) ($effectiveGateway['sim_number'] ?? 1),
                'is_custom' => (bool) $setting->use_custom_gateway,
            ] : null,
        ];
    }

    /**
     * تحديث إعدادات رسائل الشبكة مع الحماية من استبدال التوكنات أو كلمة المرور الفارغة
     */
    public function updateForNetwork(int $networkId, UpdateNetworkSmsSettingRequest $request): array
    {
        return DB::transaction(function () use ($networkId, $request) {
            $network = $this->networkRepository->findById($networkId);
            if (! $network) {
                throw ValidationException::withMessages([
                    'network_id' => ['عذراً، الشبكة المحددة غير موجودة في النظام.'],
                ]);
            }

            /** @var NetworkSmsSetting $setting */
            $setting = $this->smsSettingRepository->getOrCreateForNetwork($networkId);

            // تحديث المفتاح العام للشبكة إن أُرسل
            if ($request->has('is_enabled')) {
                $setting->is_enabled = (bool) $request->input('is_enabled');
            }

            // تحديث سويتشات الأحداث في NetSales
            $switches = [
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
            ];
            foreach ($switches as $col) {
                if ($request->has($col)) {
                    $setting->{$col} = (bool) $request->input($col);
                }
            }

            // تحديث مفتاح استخدام البوابة الخاصة
            if ($request->has('use_custom_gateway')) {
                $setting->use_custom_gateway = (bool) $request->input('use_custom_gateway');
            }

            // تحديث حقول البوابة النصية
            foreach (['server_type', 'base_url', 'auth_type', 'username', 'device_id'] as $field) {
                if ($request->has($field) && $request->input($field) !== null) {
                    $setting->{$field} = (string) $request->input($field);
                }
            }

            // عدم استبدال التوكنات أو كلمة المرور إلا إذا تم إدخال قيمة حقيقية غير فارغة
            if ($request->filled('api_token')) {
                $setting->api_token = (string) $request->input('api_token');
            }
            if ($request->filled('refresh_token')) {
                $setting->refresh_token = (string) $request->input('refresh_token');
            }
            if ($request->filled('password')) {
                $setting->password = (string) $request->input('password');
            }

            if ($request->has('sim_number')) {
                $setting->sim_number = (int) $request->input('sim_number', 1);
            }
            if ($request->has('skip_phone_validation')) {
                $setting->skip_phone_validation = (bool) $request->input('skip_phone_validation', false);
            }
            if ($request->has('device_active_within')) {
                $setting->device_active_within = (int) $request->input('device_active_within', 0);
            }

            $setting->save();

            return $this->getByNetworkId($networkId);
        });
    }

    /**
     * إرسال رسالة تجريبية لاختبار ربط بوابة الشبكة
     */
    public function testSmsForNetwork(int $networkId, TestNetworkSmsRequest $request): array
    {
        $network = $this->networkRepository->findById($networkId);
        if (! $network) {
            throw ValidationException::withMessages([
                'network_id' => ['عذراً، الشبكة المحددة غير موجودة في النظام.'],
            ]);
        }

        $phone = $request->getPhoneNumber();
        $message = "🔔 رسالة تجريبية من منصة NetSales: تم التحقق من ربط بوابة الرسائل لشبكة ({$network->network_name}) بنجاح! 🚀";

        $sent = $this->smsGateService->sendSms(
            phoneNumbers: $phone,
            message: $message,
            priority: 10,
            recipientId: $networkId,
            bypassPolicy: true,
            messageType: 'admin_broadcast'
        );

        if (! $sent) {
            throw new Exception('فشل إرسال الرسالة التجريبية. يرجى التحقق من صحة بيانات البوابة، وجود رصيد، واتصال الجهاز بالإنترنت.');
        }

        return [
            'network_id' => $networkId,
            'phone_number' => $phone,
            'sent' => true,
        ];
    }
}
