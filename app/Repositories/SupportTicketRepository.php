<?php

namespace App\Repositories;

use App\Interfaces\SupportTicketRepositoryInterface;
use App\Models\SupportTicket;
use Illuminate\Contracts\Pagination\CursorPaginator;
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
     * جلب قائمة التذاكر مع الفلترة والترقيم بالمؤشر والعلاقات.
     */
    public function getFiltered(
        array $filters = [],
        array $relations = ['network', 'pos', 'latestMessage.sentBy']
    ): Collection|CursorPaginator {
        $query = $this->model->newQuery()->with($relations);

        if (! empty($filters['network_id'])) {
            $query->forNetwork((int) $filters['network_id']);
        }

        if (! empty($filters['pos_id'])) {
            $query->forPos((int) $filters['pos_id']);
        }

        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function ($q) use ($term) {
                $q->where('ticket_subject', 'like', "%{$term}%")
                    ->orWhere('ticket_uuid', 'like', "%{$term}%");
            });
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('ticket_id', 'desc')->get();
        }

        return $query->orderBy('ticket_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * جلب التذاكر المحذوفة ناعماً مع الفلترة والترقيم بالمؤشر.
     */
    public function getTrashedFiltered(
        array $filters = [],
        array $relations = ['network', 'pos']
    ): Collection|CursorPaginator {
        $query = $this->model->newQuery()->onlyTrashed()->with($relations);

        if (! empty($filters['network_id'])) {
            $query->forNetwork((int) $filters['network_id']);
        }

        if (! empty($filters['pos_id'])) {
            $query->forPos((int) $filters['pos_id']);
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('deleted_at', 'desc')->get();
        }

        return $query->orderBy('deleted_at', 'desc')->cursorPaginate($perPage);
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
