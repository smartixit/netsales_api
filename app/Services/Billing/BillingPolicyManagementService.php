<?php

namespace App\Services\Billing;

use App\Enums\BillingSubscriptionStatus;
use App\Models\BillingDunningPolicy;
use App\Models\BillingDunningPolicyVersion;
use App\Models\BillingPlanVersion;
use App\Models\BillingPrice;
use App\Models\BillingSubscription;
use App\Models\BillingSubscriptionAlert;
use App\Models\BillingTrialAdjustment;
use App\Models\BillingTrialPolicy;
use App\Models\BillingTrialPolicyVersion;
use App\Models\BillingTrialRedemption;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingPolicyManagementService
{
    /** @param array<string, mixed> $data */
    public function createTrialPolicy(array $data, User $actor): BillingTrialPolicyVersion
    {
        return DB::transaction(function () use ($data, $actor): BillingTrialPolicyVersion {
            $this->assertPlatform($actor);
            $policy = BillingTrialPolicy::query()->create([
                'policy_key' => $data['policy_key'], 'name' => $data['name'],
                'description' => $data['description'] ?? null, 'created_by' => $actor->user_id, 'is_active' => true,
            ]);
            return $policy->versions()->create([
                ...$this->trialValues($data), 'version_number' => 1, 'status' => 'DRAFT', 'created_by' => $actor->user_id,
            ]);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function createDunningPolicy(array $data, User $actor): BillingDunningPolicyVersion
    {
        return DB::transaction(function () use ($data, $actor): BillingDunningPolicyVersion {
            $this->assertPlatform($actor);
            $policy = BillingDunningPolicy::query()->create([
                'policy_key' => $data['policy_key'], 'name' => $data['name'],
                'description' => $data['description'] ?? null, 'created_by' => $actor->user_id, 'is_active' => true,
            ]);
            return $policy->versions()->create([
                ...$this->dunningValues($data), 'version_number' => 1, 'status' => 'DRAFT', 'created_by' => $actor->user_id,
            ]);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function createTrialVersion(int $policyId, array $data, User $actor): BillingTrialPolicyVersion
    {
        return DB::transaction(function () use ($policyId, $data, $actor): BillingTrialPolicyVersion {
            $this->assertPlatform($actor);
            $policy = BillingTrialPolicy::query()->lockForUpdate()->findOrFail($policyId);
            return $policy->versions()->create([
                ...$this->trialValues($data), 'version_number' => (int) $policy->versions()->max('version_number') + 1,
                'status' => 'DRAFT', 'created_by' => $actor->user_id,
            ]);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function createDunningVersion(int $policyId, array $data, User $actor): BillingDunningPolicyVersion
    {
        return DB::transaction(function () use ($policyId, $data, $actor): BillingDunningPolicyVersion {
            $this->assertPlatform($actor);
            $policy = BillingDunningPolicy::query()->lockForUpdate()->findOrFail($policyId);
            return $policy->versions()->create([
                ...$this->dunningValues($data), 'version_number' => (int) $policy->versions()->max('version_number') + 1,
                'status' => 'DRAFT', 'created_by' => $actor->user_id,
            ]);
        }, 3);
    }

    public function publishTrialVersion(int $versionId, User $actor): BillingTrialPolicyVersion
    {
        return DB::transaction(function () use ($versionId, $actor): BillingTrialPolicyVersion {
            $this->assertPlatform($actor);
            $version = BillingTrialPolicyVersion::query()->with('policy')->lockForUpdate()->findOrFail($versionId);
            if ($version->status !== 'DRAFT') throw ValidationException::withMessages(['version_id' => ['لا يمكن نشر نسخة ليست مسودة.']]);
            $version->status = 'PUBLISHED'; $version->published_at = now('Asia/Aden'); $version->save();
            $version->policy->current_version_id = $version->trial_policy_version_id; $version->policy->save();
            return $version->refresh();
        }, 3);
    }

    public function publishDunningVersion(int $versionId, User $actor): BillingDunningPolicyVersion
    {
        return DB::transaction(function () use ($versionId, $actor): BillingDunningPolicyVersion {
            $this->assertPlatform($actor);
            $version = BillingDunningPolicyVersion::query()->with('policy')->lockForUpdate()->findOrFail($versionId);
            if ($version->status !== 'DRAFT') throw ValidationException::withMessages(['version_id' => ['لا يمكن نشر نسخة ليست مسودة.']]);
            $version->status = 'PUBLISHED'; $version->published_at = now('Asia/Aden'); $version->save();
            $version->policy->current_version_id = $version->dunning_policy_version_id; $version->policy->save();
            return $version->refresh();
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function assignToPlanVersion(int $versionId, array $data, User $actor): BillingPlanVersion
    {
        return DB::transaction(function () use ($versionId, $data, $actor): BillingPlanVersion {
            $this->assertPlatform($actor);
            $version = BillingPlanVersion::query()->lockForUpdate()->findOrFail($versionId);
            if ($version->status !== 'DRAFT') throw ValidationException::withMessages(['plan_version_id' => ['لا يمكن تغيير سياسات نسخة خطة منشورة. أنشئ نسخة خطة جديدة.']]);
            $this->assertPublishedReferences($data);
            $version->trial_policy_version_id = $data['trial_policy_version_id'] ?? null;
            $version->dunning_policy_version_id = $data['dunning_policy_version_id'] ?? null;
            $version->save();
            return $version->refresh();
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function assignToPrice(int $priceId, array $data, User $actor): BillingPrice
    {
        return DB::transaction(function () use ($priceId, $data, $actor): BillingPrice {
            $this->assertPlatform($actor);
            $price = BillingPrice::query()->with('planVersion')->lockForUpdate()->findOrFail($priceId);
            if ($price->planVersion?->status !== 'DRAFT') throw ValidationException::withMessages(['price_id' => ['لا يمكن تغيير سياسات سعر تابع لنسخة خطة منشورة.']]);
            $this->assertPublishedReferences($data);
            $price->trial_policy_version_id = $data['trial_policy_version_id'] ?? null;
            $price->dunning_policy_version_id = $data['dunning_policy_version_id'] ?? null;
            $price->save();
            return $price->refresh();
        }, 3);
    }

    public function approveTrialRedemption(int $redemptionId, User $actor): BillingSubscription
    {
        return DB::transaction(function () use ($redemptionId, $actor): BillingSubscription {
            $this->assertPlatform($actor);
            $redemption = BillingTrialRedemption::query()->lockForUpdate()->findOrFail($redemptionId);
            if ($redemption->status !== 'PENDING_APPROVAL') {
                throw ValidationException::withMessages(['trial_redemption_id' => ['طلب التجربة ليس بانتظار الموافقة.']]);
            }
            $subscription = BillingSubscription::query()->lockForUpdate()->findOrFail($redemption->billing_subscription_id);
            $snapshot = (array) $subscription->trial_policy_snapshot;
            $now = now('Asia/Aden');
            $redemption->metadata = array_merge((array) $redemption->metadata, ['approved_at' => $now->toDateTimeString(), 'approved_by' => $actor->user_id]);
            if (($snapshot['start_trigger'] ?? 'PURCHASE') === 'FIRST_ACTIVATION') {
                $redemption->status = 'RESERVED';
                $redemption->save();
                $subscription->events()->create([
                    'event_type' => 'TRIAL_APPROVED_WAITING_FIRST_ACTIVATION',
                    'from_status' => 'INCOMPLETE',
                    'to_status' => 'INCOMPLETE',
                    'payload' => ['trial_redemption_id' => $redemption->trial_redemption_id],
                    'actor_id' => $actor->user_id,
                    'occurred_at' => $now,
                ]);
                return $subscription->refresh();
            }
            $end = $this->calculateTrialEnd(\Carbon\CarbonImmutable::instance($now), $snapshot);
            $subscription->status = BillingSubscriptionStatus::TRIALING;
            $subscription->trial_start = $now;
            $subscription->trial_end = $end;
            $subscription->current_period_start = $now;
            $subscription->current_period_end = $end;
            $subscription->save();
            $redemption->status = 'ACTIVE';
            $redemption->started_at = $now;
            $redemption->save();
            app(BillingDunningService::class)->scheduleSubscriptionAlerts($subscription);
            $subscription->events()->create([
                'event_type' => 'TRIAL_APPROVED_AND_STARTED',
                'from_status' => 'INCOMPLETE',
                'to_status' => 'TRIALING',
                'payload' => ['trial_redemption_id' => $redemption->trial_redemption_id, 'trial_end' => $end->toDateTimeString()],
                'actor_id' => $actor->user_id,
                'occurred_at' => $now,
            ]);
            return $subscription->refresh();
        }, 3);
    }

    public function rejectTrialRedemption(int $redemptionId, string $reason, User $actor): BillingSubscription
    {
        return DB::transaction(function () use ($redemptionId, $reason, $actor): BillingSubscription {
            $this->assertPlatform($actor);
            $redemption = BillingTrialRedemption::query()->lockForUpdate()->findOrFail($redemptionId);
            if ($redemption->status !== 'PENDING_APPROVAL') {
                throw ValidationException::withMessages(['trial_redemption_id' => ['طلب التجربة ليس بانتظار الموافقة.']]);
            }
            $subscription = BillingSubscription::query()->lockForUpdate()->findOrFail($redemption->billing_subscription_id);
            if ($subscription->status !== BillingSubscriptionStatus::INCOMPLETE) {
                throw ValidationException::withMessages(['subscription_id' => ['لا يمكن رفض طلب التجربة بعد بدء الاشتراك.']]);
            }
            $now = now('Asia/Aden');
            $redemption->status = 'REVOKED';
            $redemption->completed_at = $now;
            $redemption->metadata = array_merge((array) $redemption->metadata, [
                'rejected_at' => $now->toDateTimeString(),
                'rejected_by' => $actor->user_id,
                'rejection_reason' => $reason,
            ]);
            $redemption->save();

            $subscription->status = BillingSubscriptionStatus::EXPIRED;
            $subscription->ended_at = $now;
            $subscription->save();
            $subscription->events()->create([
                'event_type' => 'TRIAL_REDEMPTION_REJECTED',
                'from_status' => BillingSubscriptionStatus::INCOMPLETE->value,
                'to_status' => BillingSubscriptionStatus::EXPIRED->value,
                'payload' => ['trial_redemption_id' => $redemption->trial_redemption_id, 'reason' => $reason],
                'actor_id' => $actor->user_id,
                'occurred_at' => $now,
            ]);
            return $subscription->refresh();
        }, 3);
    }

    public function adjustTrial(int $subscriptionId, string $type, ?int $days, string $reason, User $actor): BillingSubscription
    {
        return DB::transaction(function () use ($subscriptionId, $type, $days, $reason, $actor): BillingSubscription {
            $this->assertPlatform($actor);
            $subscription = BillingSubscription::query()->with('trialRedemption')->lockForUpdate()->findOrFail($subscriptionId);
            if ($subscription->status !== BillingSubscriptionStatus::TRIALING || ! $subscription->trialRedemption) throw ValidationException::withMessages(['subscription_id' => ['لا يمكن تعديل فترة تجربة غير نشطة.']]);
            if (! in_array($type, ['EXTEND', 'SHORTEN', 'END_EARLY'], true) || ($type !== 'END_EARLY' && ($days === null || $days < 1 || $days > 365))) throw ValidationException::withMessages(['adjustment_type' => ['نوع التعديل أو عدد الأيام غير صالح.']]);
            $previous = $subscription->trial_end;
            $newEnd = match ($type) {
                'EXTEND' => $previous->copy()->addDays($days),
                'SHORTEN' => $previous->copy()->subDays($days),
                'END_EARLY' => now('Asia/Aden'),
            };
            if ($newEnd->lte(now('Asia/Aden'))) {
                $subscription->status = BillingSubscriptionStatus::EXPIRED;
                $subscription->ended_at = now('Asia/Aden');
                $subscription->trialRedemption()->update(['status' => 'COMPLETED', 'completed_at' => now('Asia/Aden')]);
                BillingSubscriptionAlert::query()->where('billing_subscription_id', $subscriptionId)->where('status', 'PENDING')->update(['status' => 'CANCELLED']);
            } else {
                BillingSubscriptionAlert::query()->where('billing_subscription_id', $subscriptionId)->where('status', 'PENDING')->update(['status' => 'CANCELLED']);
            }
            $subscription->trial_end = $newEnd; $subscription->current_period_end = $newEnd; $subscription->save();
            if ($subscription->status === BillingSubscriptionStatus::TRIALING) app(BillingDunningService::class)->scheduleSubscriptionAlerts($subscription);
            BillingTrialAdjustment::query()->create([
                'trial_redemption_id' => $subscription->trialRedemption->trial_redemption_id,
                'billing_subscription_id' => $subscriptionId, 'adjustment_type' => $type,
                'previous_trial_end' => $previous, 'new_trial_end' => $newEnd,
                'reason' => $reason, 'actor_id' => $actor->user_id, 'occurred_at' => now('Asia/Aden'),
            ]);
            $subscription->events()->create([
                'event_type' => 'TRIAL_'.$type, 'from_status' => 'TRIALING', 'to_status' => $subscription->status->value,
                'payload' => ['previous_trial_end' => $previous?->toDateTimeString(), 'new_trial_end' => $newEnd->toDateTimeString(), 'reason' => $reason],
                'actor_id' => $actor->user_id, 'occurred_at' => now('Asia/Aden'),
            ]);
            return $subscription->refresh();
        }, 3);
    }

    /** @param array<string, mixed> $snapshot */
    private function calculateTrialEnd(\Carbon\CarbonImmutable $start, array $snapshot): \Carbon\CarbonImmutable
    {
        return match ($snapshot['duration_unit']) {
            'HOUR' => $start->addHours((int) $snapshot['duration_value']),
            'DAY' => $start->addDays((int) $snapshot['duration_value']),
            'WEEK' => $start->addWeeks((int) $snapshot['duration_value']),
            'MONTH' => $start->addMonthsNoOverflow((int) $snapshot['duration_value']),
            default => throw ValidationException::withMessages(['trial_policy' => ['وحدة مدة التجربة غير مدعومة.']]),
        };
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
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

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function dunningValues(array $data): array
    {
        return [
            'grace_period_days' => (int) ($data['grace_period_days'] ?? 7),
            'auto_suspend_after_grace' => (bool) ($data['auto_suspend_after_grace'] ?? true),
            'reminder_schedule_days' => $data['reminder_schedule_days'] ?? [0, 1, 3, 7],
            'pre_renewal_alert_days' => $data['pre_renewal_alert_days'] ?? [3],
        ];
    }

    /** @param array<string, mixed> $data */
    /**
     * @param array<string, mixed> $data
     */
    private function assertPublishedReferences(array $data): void
    {
        $trialPolicyVersionId = $data['trial_policy_version_id'] ?? null;
        if ($trialPolicyVersionId !== null) {
            $trialVersion = BillingTrialPolicyVersion::query()->find($trialPolicyVersionId);
            if (! $trialVersion || $trialVersion->status !== 'PUBLISHED') {
                throw ValidationException::withMessages([
                    'trial_policy_version_id' => ['يجب اختيار إصدار سياسة تجربة منشور.'],
                ]);
            }
        }

        $dunningPolicyVersionId = $data['dunning_policy_version_id'] ?? null;
        if ($dunningPolicyVersionId !== null) {
            $dunningVersion = BillingDunningPolicyVersion::query()->find($dunningPolicyVersionId);
            if (! $dunningVersion || $dunningVersion->status !== 'PUBLISHED') {
                throw ValidationException::withMessages([
                    'dunning_policy_version_id' => ['يجب اختيار إصدار سياسة تحصيل منشور.'],
                ]);
            }
        }
    }

    private function assertPlatform(User $actor): void
    {
        app(BillingAuthorizationService::class)->assertPlatform($actor);
    }
}
