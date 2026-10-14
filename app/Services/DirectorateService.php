<?php

namespace App\Services;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Geography\StoreDirectorateRequest;
use App\Http\Requests\API\V1\Geography\UpdateDirectorateRequest;
use App\Interfaces\DirectorateRepositoryInterface;
use App\Interfaces\GovernorateRepositoryInterface;
use App\Models\Directorate;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة المديريات والمناطق الفرعية (Directorate Service).
 *
 * المسؤولة عن معالجة طلبات المديريات وضمان التكامل مع المحافظات وحماية العمليات التابعة.
 */
class DirectorateService
{
    public function __construct(
        protected DirectorateRepositoryInterface $directorateRepository,
        protected GovernorateRepositoryInterface $governorateRepository
    ) {}

    /**
     * جلب قائمة المديريات المفلترة مع الترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->directorateRepository->getFiltered($filters, ['governorate']);
    }

    /**
     * جلب المديريات المحذوفة ناعماً.
     */
    public function getTrashedFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->directorateRepository->getTrashedFiltered($filters, ['governorate']);
    }

    /**
     * جلب كافة مديريات محافظة معينة.
     */
    public function getByGovernorate(int $governorateId): Collection
    {
        return $this->directorateRepository->getByGovernorate($governorateId);
    }

    /**
     * جلب تفاصيل مديرية محددة.
     */
    public function getById(int $id): Directorate
    {
        /** @var Directorate|null $directorate */
        $directorate = $this->directorateRepository->findById($id);

        if (! $directorate) {
            throw ValidationException::withMessages([
                'directorate_id' => ["المديرية رقم [{$id}] غير موجودة بالنظام."],
            ]);
        }

        $directorate->loadMissing('governorate');

        return $directorate;
    }

    /**
     * إنشاء مديرية جديدة.
     */
    public function create(StoreDirectorateRequest $request): Directorate
    {
        return DB::transaction(function () use ($request) {
            $data = [
                'governorate_id' => $request->getGovernorateId(),
                'directorate_name' => $request->getDirectorateName(),
            ];

            return $this->directorateRepository->create($data);
        });
    }

    /**
     * تحديث بيانات مديرية قائمة.
     */
    public function update(UpdateDirectorateRequest $request, int $id): Directorate
    {
        return DB::transaction(function () use ($request, $id) {
            $directorate = $this->getById($id);

            $data = [];
            if ($request->getGovernorateId() !== null) {
                $data['governorate_id'] = $request->getGovernorateId();
            }
            if ($request->getDirectorateName() !== null) {
                $data['directorate_name'] = $request->getDirectorateName();
            }

            if (! empty($data)) {
                $this->directorateRepository->update($directorate->directorate_id, $data);
            }

            return $this->getById($id);
        });
    }

    /**
     * حذف مديرية ناعماً مع التحقق من عدم وجود شبكات أو نقاط بيع مرتبطة بها.
     */
    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $directorate = $this->getById($id);

            if ($directorate->networksCount() > 0 || $directorate->posCount() > 0) {
                throw ValidationException::withMessages([
                    'directorate_id' => ["لا يمكن حذف المديرية [{$directorate->directorate_name}] لوجود شبكات أو نقاط بيع مرتبطة بها."],
                ]);
            }

            return $this->directorateRepository->delete($directorate->directorate_id);
        });
    }

    /**
     * استعادة مديرية محذوفة ناعماً.
     */
    public function restore(int $id): Directorate
    {
        $this->directorateRepository->restore($id);

        return $this->getById($id);
    }

    /**
     * الحذف النهائي لمديرية من قاعدة البيانات.
     */
    public function forceDelete(int $id): bool
    {
        return $this->directorateRepository->forceDelete($id);
    }

    /**
     * الحذف المجمع للمديريات ناعماً.
     */
    public function bulkDelete(BatchIdsRequest $request): int
    {
        return $this->directorateRepository->bulkDelete($request->getIds());
    }

    /**
     * الاستعادة المجمعة للمديريات.
     */
    public function bulkRestore(BatchIdsRequest $request): int
    {
        return $this->directorateRepository->bulkRestore($request->getIds());
    }

    /**
     * الحذف النهائي المجمع للمديريات.
     */
    public function bulkForceDelete(BatchIdsRequest $request): int
    {
        return $this->directorateRepository->bulkForceDelete($request->getIds());
    }
}
