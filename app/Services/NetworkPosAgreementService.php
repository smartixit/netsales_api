<?php

namespace App\Services;

use App\Models\NetworkPos;
use App\Models\NetworkPosAgreement;
use App\Models\NetworkPosEvent;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NetworkPosAgreementService
{
    public function agreements(int $linkId, array $filters = []): CursorPaginator
    {
        return NetworkPosAgreement::query()
            ->where('network_pos_id', $linkId)
            ->orderByDesc('version')
            ->cursorPaginate(min(max((int) ($filters['per_page'] ?? 15), 1), 50), ['*'], 'cursor', $filters['cursor'] ?? null);
    }

    public function createDraft(int $linkId, array $data, ?int $actorId): NetworkPosAgreement
    {
        return DB::transaction(function () use ($linkId, $data, $actorId): NetworkPosAgreement {
            $link = NetworkPos::query()->lockForUpdate()->findOrFail($linkId);
            if ($link->trashed()) {
                throw ValidationException::withMessages(['network_pos_id' => ['لا يمكن إنشاء اتفاقية لعلاقة مؤرشفة.']]);
            }

            $this->assertDateRange($data['starts_at'] ?? null, $data['ends_at'] ?? null);
            $version = ((int) NetworkPosAgreement::query()->where('network_pos_id', $linkId)->max('version')) + 1;
            $agreement = NetworkPosAgreement::query()->create([
                'network_pos_id' => $linkId,
                'version' => $version,
                'title' => $data['title'],
                'reference' => $data['reference'] ?? null,
                'terms' => $data['terms'],
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'status' => 'DRAFT',
                'created_by' => $actorId,
            ]);
            $this->record($linkId, $actorId, 'AGREEMENT_DRAFT_CREATED', [
                'agreement_id' => $agreement->agreement_id,
                'version' => $version,
                'title' => $agreement->title,
                'reference' => $agreement->reference,
            ]);

            return $agreement;
        });
    }

    public function updateDraft(int $linkId, int $agreementId, array $data, ?int $actorId): NetworkPosAgreement
    {
        return DB::transaction(function () use ($linkId, $agreementId, $data, $actorId): NetworkPosAgreement {
            $link = NetworkPos::query()->lockForUpdate()->findOrFail($linkId);
            $agreement = NetworkPosAgreement::query()->where('network_pos_id', $linkId)->lockForUpdate()->findOrFail($agreementId);
            if ($agreement->status !== 'DRAFT' || $link->trashed()) {
                throw ValidationException::withMessages(['agreement' => ['يمكن تعديل مسودات الاتفاقيات فقط ضمن علاقة نشطة.']]);
            }
            $start = array_key_exists('starts_at', $data) ? $data['starts_at'] : $agreement->starts_at;
            $end = array_key_exists('ends_at', $data) ? $data['ends_at'] : $agreement->ends_at;
            $this->assertDateRange($start, $end);
            $agreement->fill($data)->save();
            $this->record($linkId, $actorId, 'AGREEMENT_DRAFT_UPDATED', [
                'agreement_id' => $agreement->agreement_id,
                'version' => $agreement->version,
                'changed_fields' => array_keys($data),
            ]);

            return $agreement->refresh();
        });
    }

    public function activate(int $linkId, int $agreementId, ?int $actorId): NetworkPosAgreement
    {
        return DB::transaction(function () use ($linkId, $agreementId, $actorId): NetworkPosAgreement {
            $link = NetworkPos::query()->lockForUpdate()->findOrFail($linkId);
            if (! $link->isActive() || $link->trashed()) {
                throw ValidationException::withMessages(['network_pos_id' => ['يجب أن تكون العلاقة نشطة قبل اعتماد الاتفاقية.']]);
            }
            $agreement = NetworkPosAgreement::query()->where('network_pos_id', $linkId)->lockForUpdate()->findOrFail($agreementId);
            if ($agreement->status !== 'DRAFT') {
                throw ValidationException::withMessages(['agreement' => ['لا يمكن اعتماد إلا مسودة اتفاقية.']]);
            }
            if ($agreement->starts_at && $agreement->starts_at->isFuture()) {
                throw ValidationException::withMessages(['starts_at' => ['لا يمكن اعتماد اتفاقية قبل تاريخ بدء سريانها.']]);
            }
            if ($agreement->ends_at && $agreement->ends_at->isPast()) {
                throw ValidationException::withMessages(['ends_at' => ['انتهت مدة الاتفاقية ولا يمكن اعتمادها.']]);
            }
            $expiredAgreements = NetworkPosAgreement::query()
                ->where('network_pos_id', $linkId)
                ->where('status', 'ACTIVE')
                ->whereNotNull('ends_at')
                ->where('ends_at', '<=', now('Asia/Aden'))
                ->lockForUpdate()
                ->get();

            foreach ($expiredAgreements as $expiredAgreement) {
                $expiredAgreement->update(['status' => 'EXPIRED']);
                $this->record($linkId, $actorId, 'AGREEMENT_EXPIRED', [
                    'agreement_id' => $expiredAgreement->agreement_id,
                    'version' => $expiredAgreement->version,
                ]);
            }

            $hasActive = NetworkPosAgreement::query()->where('network_pos_id', $linkId)->where('status', 'ACTIVE')->exists();
            if ($hasActive) {
                throw ValidationException::withMessages(['agreement' => ['توجد اتفاقية نشطة بالفعل. أنهِ الاتفاقية الحالية قبل اعتماد إصدار جديد.']]);
            }
            $agreement->update([
                'status' => 'ACTIVE',
                'activated_by' => $actorId,
                'activated_at' => now('Asia/Aden'),
            ]);
            $this->record($linkId, $actorId, 'AGREEMENT_ACTIVATED', [
                'agreement_id' => $agreement->agreement_id,
                'version' => $agreement->version,
            ]);

            return $agreement->refresh();
        });
    }

    public function terminate(int $linkId, int $agreementId, ?int $actorId): NetworkPosAgreement
    {
        return DB::transaction(function () use ($linkId, $agreementId, $actorId): NetworkPosAgreement {
            NetworkPos::query()->lockForUpdate()->findOrFail($linkId);
            $agreement = NetworkPosAgreement::query()->where('network_pos_id', $linkId)->lockForUpdate()->findOrFail($agreementId);
            if ($agreement->status !== 'ACTIVE') {
                throw ValidationException::withMessages(['agreement' => ['لا يمكن إنهاء إلا اتفاقية نشطة.']]);
            }
            $agreement->update(['status' => 'TERMINATED', 'terminated_at' => now('Asia/Aden')]);
            $this->record($linkId, $actorId, 'AGREEMENT_TERMINATED', [
                'agreement_id' => $agreement->agreement_id,
                'version' => $agreement->version,
            ]);

            return $agreement->refresh();
        });
    }

    public function events(int $linkId, array $filters = []): CursorPaginator
    {
        return NetworkPosEvent::query()->with('actor')
            ->where('network_pos_id', $linkId)
            ->orderByDesc('event_id')
            ->cursorPaginate(min(max((int) ($filters['per_page'] ?? 20), 1), 50), ['*'], 'cursor', $filters['cursor'] ?? null);
    }

    private function assertDateRange(mixed $start, mixed $end): void
    {
        if ($start !== null && $end !== null
            && Carbon::parse($end, 'Asia/Aden')->lessThanOrEqualTo(Carbon::parse($start, 'Asia/Aden'))) {
            throw ValidationException::withMessages([
                'ends_at' => ['تاريخ انتهاء الاتفاقية يجب أن يكون بعد تاريخ بدايتها.'],
            ]);
        }
    }

    private function record(int $linkId, ?int $actorId, string $type, array $payload): void
    {
        NetworkPosEvent::query()->create([
            'network_pos_id' => $linkId,
            'actor_user_id' => $actorId,
            'event_type' => $type,
            'payload' => $payload,
        ]);
    }
}
