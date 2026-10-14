<?php

namespace App\Interfaces;

use App\Models\SupportTicket;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع تذاكر الدعم الفني (Support Ticket Repository Interface).
 */
interface SupportTicketRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب تفاصيل تذكرة عبر المعرف الفريد للأعمال (ticket_uuid).
     */
    public function findByUuid(string $uuid): ?SupportTicket;

    /**
     * جلب قائمة التذاكر مع الفلترة والترقيم بالمؤشر والعلاقات.
     */
    public function getFiltered(
        array $filters = [],
        array $relations = ['network', 'pos', 'latestMessage.sentBy']
    ): Collection|CursorPaginator;

    /**
     * جلب التذاكر المحذوفة ناعماً مع الفلترة والترقيم بالمؤشر.
     */
    public function getTrashedFiltered(
        array $filters = [],
        array $relations = ['network', 'pos']
    ): Collection|CursorPaginator;

    /**
     * حساب عدد الرسائل غير المقروءة لشبكة معينة.
     */
    public function countUnreadForNetwork(int $networkId): int;

    /**
     * حساب عدد الرسائل غير المقروءة لنقطة بيع معينة.
     */
    public function countUnreadForPos(int $posId): int;
}
