<?php

namespace App\Repositories;

use App\Interfaces\SupportTicketMessageRepositoryInterface;
use App\Models\SupportTicketMessage;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع إدارة رسائل تذاكر الدعم الفني (Support Ticket Message Repository).
 *
 * @property SupportTicketMessage $model
 */
class SupportTicketMessageRepository extends BaseRepository implements SupportTicketMessageRepositoryInterface
{
    public function __construct(SupportTicketMessage $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب كافة رسائل تذكرة معينة مرتبة زمنياً.
     */
    public function getByTicketId(int $ticketId, array $relations = ['sentBy']): Collection
    {
        return $this->model->newQuery()
            ->with($relations)
            ->where('ticket_id', $ticketId)
            ->orderBy('sent_at', 'asc')
            ->orderBy('message_id', 'asc')
            ->get();
    }

    /**
     * تحديد كافة رسائل التذكرة غير المقروءة كمقروءة بالقفل المتشائم.
     */
    public function markAsReadForTicket(int $ticketId, int $readerUserId): int
    {
        $unreadMessages = $this->model->newQuery()
            ->where('ticket_id', $ticketId)
            ->unread()
            ->where('sent_by', '!=', $readerUserId)
            ->lockForUpdate()
            ->get();

        $count = 0;

        foreach ($unreadMessages as $message) {
            $message->markAsRead();

            $this->recordModification($message, ['is_read' => false]);
            $count++;
        }

        return $count;
    }

    /**
     * حساب عدد الرسائل غير المقروءة لتذكرة محددة.
     */
    public function countUnreadForTicket(int $ticketId): int
    {
        return $this->model->newQuery()
            ->where('ticket_id', $ticketId)
            ->unread()
            ->count();
    }
}
