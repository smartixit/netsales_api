<?php

namespace App\Services\Billing;

use App\Models\{BillingDunningPolicy, BillingDunningPolicyVersion, BillingPlanVersion, BillingPrice, BillingTrialPolicy, BillingTrialPolicyVersion, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingPolicyManagementService
{
    public function createTrialPolicy(array $data, User $actor): BillingTrialPolicyVersion
    {
        return DB::transaction(function () use ($data, $actor): BillingTrialPolicyVersion {
            $this->assertPlatform($actor);
            $policy = BillingTrialPolicy::query()->create([
                'policy_key' => $data['policy_key'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'created_by' => $actor->user_id,
                'is_active' => true,
            ]);
            return $policy->versions()->create([
                ...$this->trialValues($data),
                'version_number' => 1,
                'status' => 'DRAFT',
                'created_by' => $actor->user_id,
            ]);
        }, 3);
    }

    public function createDunningPolicy(array $data, User $actor): BillingDunningPolicyVersion
    {
        return DB::transaction(function () use ($data, $actor): BillingDunningPolicyVersion {
            $this->assertPlatform($actor);
            $policy = BillingDunningPolicy::query()->create([
                'policy_key' => $data['policy_key'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'created_by' => $actor->user_id,
                'is_active' => true,
            ]);
            return $policy->versions()->create([
                ...$this->dunningValues($data),
                'version_number' => 1,
                'status' => 'DRAFT',
                'created_by' => $actor->user_id,
            ]);
        }, 3);
    }

    public function createTrialVersion(int $policyId, array $data, User $actor): BillingTrialPolicyVersion
    {
        return DB::transaction(function () use ($policyId, $data, $actor): BillingTrialPolicyVersion {
            $this->assertPlatform($actor);
            $policy = BillingTrialPolicy::query()->lockForUpdate()->findOrFail($policyId);
            $number = (int) $policy->versions()->max('version_number') + 1;
            return $policy->versions()->create([
                ...$this->trialValues($data),
                'version_number' => $number,
                'status' => 'DRAFT',
                'created_by' => $actor->user_id,
            ]);
        }, 3);
    }

    public function createDunningVersion(int $policyId, array $data, User $actor): BillingDunningPolicyVersion
    {
        return DB::transaction(function () use ($policyId, $data, $actor): BillingDunningPolicyVersion {
            $this->assertPlatform($actor);
            $policy = BillingDunningPolicy::query()->lockForUpdate()->findOrFail($policyId);
            $number = (int) $policy->versions()->max('version_number') + 1;
            return $policy->versions()->create([
                ...$this->dunningValues($data),
                'version_number' => $number,
                'status' => 'DRAFT',
                'created_by' => $actor->user_id,
            ]);
        }, 3);
    }

    public function publishTrialVersion(int $versionId, User $actor): BillingTrialPolicyVersion
    {
        return DB::transaction(function () use ($versionId, $actor): BillingTrialPolicyVersion {
            $this->assertPlatform($actor);
            $version = BillingTrialPolicyVersion::query()->with('policy')->lockForUpdate()->findOrFail($versionId);
            if ($version->status !== 'DRAFT') {
                throw ValidationException::withMessages(['version_id' => ['لا يمكن نشر نسخة ليست مسودة.']]);
            }
            $version->status = 'PUBLISHED';
            $version->published_at = now('Asia/Aden');
            $version->save();
            $version->policy->current_version_id = $version->trial_policy_version_id;
            $version->policy->save();
            return $version->refresh();
        }, 3);
    }

    public function publishDunningVersion(int $versionId, User $actor): BillingDunningPolicyVersion
    {
        return DB::transaction(function () use ($versionId, $actor): BillingDunningPolicyVersion {
            $this->assertPlatform($actor);
            $version = BillingDunningPolicyVersion::query()->with('policy')->lockForUpdate()->findOrFail($versionId);
            if ($version->status !== 'DRAFT') {
                throw ValidationException::withMessages(['version_id' => ['لا يمكن نشر نسخة ليست مسودة.']]);
            }
            $version->status = 'PUBLISHED';
            $version->published_at = now('Asia/Aden');
            $version->save();
            $version->policy->current_version_id = $version->dunning_policy_version_id;
            $version->policy->save();
            return $version->refresh();
        }, 3);
    }

    public function assignToPlanVersion(int $versionId, array $data, User $actor): BillingPlanVersion
    {
        return DB::transaction(function () use ($versionId, $data, $actor): BillingPlanVersion {
            $this->assertPlatform($actor);
            $version = BillingPlanVersion::query()->lockForUpdate()->findOrFail($versionId);
            if ($version->status !== 'DRAFT') {
                throw ValidationException::withMessages(['plan_version_id' => ['لا يمكن تغيير سياسات نسخة خطة منشورة. أنشئ نسخة خطة جديدة.']]);
            }
            $this->assertPublishedReferences($data);
            $version->trial_policy_version_id = $data['trial_policy_version_id'] ?? null;
            $version->dunning_policy_version_id = $data['dunning_policy_version_id'] ?? null;
            $version->save();
            return $version->refresh();
        }, 3);
    }

    public function assignToPrice(int $priceId, array $data, User $actor): BillingPrice
    {
        return DB::transaction(function () use ($priceId, $data, $actor): BillingPrice {
            $this->assertPlatform($actor);
            $price = BillingPrice::query()->with('planVersion')->lockForUpdate()->findOrFail($priceId);
            if ($price->planVersion?->status !== 'DRAFT') {
                throw ValidationException::withMessages(['price_id' => ['لا يمكن تغيير سياسات سعر تابع لنسخة خطة منشورة.']]);
            }
            $this->assertPublishedReferences($data);
            $price->trial_policy_version_id = $data['trial_policy_version_id'] ?? null;
            $price->dunning_policy_version_id = $data['dunning_policy_version_id'] ?? null;
            $price->save();
            return $price->refresh();
        }, 3);
    }

    private function trialValues(array $data): array
    {
        return [
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'duration_value' => (int) ($data['duration_value'] ?? 0),
            'duration_unit' => $data['duration_unit'] ?? 'DAY',
            'start_trigger' => $data['start_trigger'] ?? 'PURCHASE',
            'redemption_scope' => $data['redemption_scope'] ?? 'NETWORK',
            'eligibility_mode' => $data['eligibility_mode'] ?? 'NEW_BILLING_ACCOUNT',
            'requires_manual_approval' => (bool) ($data['requires_manual_approval'] ?? false),
            'end_behavior' => $data['end_behavior'] ?? 'CREATE_INVOICE',
            'entitlement_mode' => $data['entitlement_mode'] ?? 'FULL_PLAN',
            'entitlement_config' => $data['entitlement_config'] ?? null,
        ];
    }

    private function dunningValues(array $data): array
    {
        return [
            'grace_period_days' => (int) ($data['grace_period_days'] ?? 7),
            'auto_suspend_after_grace' => (bool) ($data['auto_suspend_after_grace'] ?? true),
            'reminder_schedule_days' => $data['reminder_schedule_days'] ?? [0, 1, 3, 7],
            'pre_renewal_alert_days' => $data['pre_renewal_alert_days'] ?? [3],
        ];
    }

    private function assertPublishedReferences(array $data): void
    {
        foreach (['trial_policy_version_id' => BillingTrialPolicyVersion::class, 'dunning_policy_version_id' => BillingDunningPolicyVersion::class] as $key => $model) {
            if (! array_key_exists($key, $data) || $data[$key] === null) continue;
            $version = $model::query()->find($data[$key]);
            if (! $version || $version->status !== 'PUBLISHED') {
                throw ValidationException::withMessages([$key => ['يجب اختيار إصدار سياسة منشور.']]);
            }
        }
    }

    private function assertPlatform(User $actor): void
    {
        app(BillingAuthorizationService::class)->assertPlatform($actor);
    }
}
