<?php

namespace App\Services;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Geography\StoreGovernorateRequest;
use App\Http\Requests\API\V1\Geography\UpdateGovernorateRequest;
use App\Interfaces\GovernorateRepositoryInterface;
use App\Models\Governorate;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة المحافظات والمناطق الجغرافية (Governorate Service).
 *
 * المسؤولة عن منطق الأعمال للمحافظات والمعاملات وحماية التكامل المرجعي.
 */
class GovernorateService
{
    public function __construct(
        protected GovernorateRepositoryInterface $governorateRepository
    ) {}

    /**
     * جلب قائمة المحافظات المفلترة مع الترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->governorateRepository->getFiltered($filters, ['directorates']);
    }

    /**
     * جلب المحافظات المحذوفة ناعماً.
     */
    public function getTrashedFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->governorateRepository->getTrashedFiltered($filters, ['directorates']);
    }

    /**
     * جلب كافة المحافظات مع إحصائيات المديريات والشبكات.
     */
    public function getWithCounts(): Collection
    {
        return $this->governorateRepository->getWithCounts();
    }

    /**
     * جلب تفاصيل محافظة محددة مع التحقق من وجودها.
     */
    public function getById(int $id): Governorate
    {
        /** @var Governorate|null $governorate */
        $governorate = $this->governorateRepository->findById($id);

        if (! $governorate) {
            throw ValidationException::withMessages([
                'governorate_id' => ["المحافظة رقم [{$id}] غير موجودة بالنظام."],
            ]);
        }

        $governorate->loadMissing('directorates');

        return $governorate;
    }

    /**
     * إنشاء محافظة جديدة.
     */
    public function create(StoreGovernorateRequest $request): Governorate
    {
        return DB::transaction(function () use ($request) {
            $data = [
                'governorate_name' => $request->getGovernorateName(),
            ];

            return $this->governorateRepository->create($data);
        });
    }

    /**
     * تحديث بيانات محافظة قائمة.
     */
    public function update(UpdateGovernorateRequest $request, int $id): Governorate
    {
        return DB::transaction(function () use ($request, $id) {
            $governorate = $this->getById($id);

            $data = [];
            if ($request->getGovernorateName() !== null) {
                $data['governorate_name'] = $request->getGovernorateName();
            }

            if (! empty($data)) {
                $this->governorateRepository->update($governorate->governorate_id, $data);
            }

            return $this->getById($id);
        });
    }

    /**
     * حذف محافظة ناعماً مع التحقق من عدم ارتباط مديريات نشطة بها.
     */
    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $governorate = $this->getById($id);

            if ($governorate->directoratesCount() > 0 || $governorate->networksCount() > 0 || $governorate->posCount() > 0) {
                throw ValidationException::withMessages([
                    'governorate_id' => ["لا يمكن حذف المحافظة [{$governorate->governorate_name}] لوجود مديريات أو شبكات أو نقاط بيع مرتبطة بها."],
                ]);
            }

            return $this->governorateRepository->delete($governorate->governorate_id);
        });
    }

    /**
     * استعادة محافظة محذوفة ناعماً.
     */
    public function restore(int $id): Governorate
    {
        $this->governorateRepository->restore($id);

        return $this->getById($id);
    }

    /**
     * الحذف النهائي لمحافظة من قاعدة البيانات.
     */
    public function forceDelete(int $id): bool
    {
        return $this->governorateRepository->forceDelete($id);
    }

    /**
     * الحذف المجمع للمحافظات ناعماً.
     */
    public function bulkDelete(BatchIdsRequest $request): int
    {
        return $this->governorateRepository->bulkDelete($request->getIds());
    }

    /**
     * الاستعادة المجمعة للمحافظات.
     */
    public function bulkRestore(BatchIdsRequest $request): int
    {
        return $this->governorateRepository->bulkRestore($request->getIds());
    }

    /**
     * الحذف النهائي المجمع للمحافظات.
     */
    public function bulkForceDelete(BatchIdsRequest $request): int
    {
        return $this->governorateRepository->bulkForceDelete($request->getIds());
    }
}
