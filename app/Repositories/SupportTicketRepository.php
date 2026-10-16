<?php

namespace App\Repositories;

use App\Interfaces\SupportTicketRepositoryInterface;
use App\Models\SupportTicket;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع إدارة تذاكر الدعم الفني (Support Ticket Repository).
 *
 * @property SupportTicket $model
 */
class SupportTicketRepository extends BaseRepository implements SupportTicketRepositoryInterface
{
    public function __construct(SupportTicket $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب تفاصيل تذكرة عبر المعرف الفريد للأعمال (ticket_uuid).
     */
    public function findByUuid(string $uuid): ?SupportTicket
    {
        return $this->model->newQuery()
            ->with(['network', 'pos', 'messages.sentBy', 'latestMessage.sentBy'])
            ->where('ticket_uuid', $uuid)
            ->first();
    }

    /**
     * تطبيق الفلاتر الخاصة بتذاكر الدعم الفني.
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        parent::applyFilters($query, $filters);

        if (! empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $query->where(function ($q) use ($term) {
                $q->where('ticket_subject', 'like', "%{$term}%")
                    ->orWhere('ticket_uuid', 'like', "%{$term}%");
            });
        }
    }

    /**
     * جلب قائمة التذاكر مع الفلترة والترقيم بالمؤشر والعلاقات.
     */
    public function getFiltered(
        array $filters = [],
        array $relations = ['network', 'pos', 'latestMessage.sentBy']
    ): Collection|CursorPaginator {
        return parent::getFiltered($filters, $relations);
    }

    /**
     * جلب التذاكر المحذوفة ناعماً مع الفلترة والترقيم بالمؤشر.
     */
    public function getTrashedFiltered(
        array $filters = [],
        array $relations = ['network', 'pos']
    ): Collection|CursorPaginator {
        if (! $this->usesSoftDeletes()) {
            return collect();
        }

        $query = $this->buildBaseFilteredQuery($filters, $relations, true);

        return $this->paginateOrAll($query, $filters, 'deleted_at');
    }

    /**
     * حساب عدد الرسائل غير المقروءة لشبكة معينة.
     */
    public function countUnreadForNetwork(int $networkId): int
    {
        return $this->model->newQuery()
            ->forNetwork($networkId)
            ->whereHas('messages', fn ($q) => $q->unread())
            ->count();
    }

    /**
     * حساب عدد الرسائل غير المقروءة لنقطة بيع معينة.
     */
    public function countUnreadForPos(int $posId): int
    {
        return $this->model->newQuery()
            ->forPos($posId)
            ->whereHas('messages', fn ($q) => $q->unread())
            ->count();
    }
}
