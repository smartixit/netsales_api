<?php

namespace App\Interfaces;

use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع رسائل تذاكر الدعم الفني (Support Ticket Message Repository Interface).
 */
interface SupportTicketMessageRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب كافة رسائل تذكرة معينة مرتبة زمنياً.
     */
    public function getByTicketId(int $ticketId, array $relations = ['sentBy']): Collection;

    /**
     * تحديد كافة رسائل التذكرة غير المقروءة كمقروءة بالقفل المتشائم.
     */
    public function markAsReadForTicket(int $ticketId, int $readerUserId): int;

    /**
     * حساب عدد الرسائل غير المقروءة لتذكرة محددة.
     */
    public function countUnreadForTicket(int $ticketId): int;
}
