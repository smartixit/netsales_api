<?php

namespace App\Services;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Ticket\StoreSupportTicketMessageRequest;
use App\Http\Requests\API\V1\Ticket\StoreSupportTicketRequest;
use App\Http\Requests\API\V1\Ticket\UpdateSupportTicketRequest;
use App\Interfaces\NetworkRepositoryInterface;
use App\Interfaces\NetworkPosRepositoryInterface;
use App\Interfaces\PosRepositoryInterface;
use App\Interfaces\SupportTicketMessageRepositoryInterface;
use App\Interfaces\SupportTicketRepositoryInterface;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * خدمة إدارة تذاكر الدعم الفني والمحادثات التفاعلية (Support Ticket Service).
 *
 * تلتزم بقواعد الكود النظيف الموحدة:
 * 1. منع استدعاء $request->validated() نهائياً.
 * 2. إدارة معاملات DB::transaction حصرياً داخل طبقة الخدمة.
 * 3. التنسيق بين المستودعات دون استعلامات Eloquent مباشرة على النماذج.
 */
class SupportTicketService
{
    public function __construct(
        protected SupportTicketRepositoryInterface $ticketRepository,
        protected SupportTicketMessageRepositoryInterface $messageRepository,
        protected NetworkRepositoryInterface $networkRepository,
        protected PosRepositoryInterface $posRepository,
        protected NetworkPosRepositoryInterface $networkPosRepository,
        protected TenantPermissionService $tenantPermissions
    ) {}

    /**
     * جلب قائمة تذاكر الدعم الفني مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters, int $actorId): Collection|CursorPaginator
    {
        $filters = $this->applyAuthorizedTenantFilters($filters, $actorId, 'support_tickets.view');

        return $this->ticketRepository->getFiltered($filters);
    }

    /**
     * جلب قائمة التذاكر المحذوفة ناعماً.
     */
    public function getTrashedFiltered(array $filters = []): Collection|CursorPaginator
    {
        return $this->ticketRepository->getTrashedFiltered($filters);
    }

    /**
     * جلب تفاصيل تذكرة محددة عبر معرفها الرقمي.
     */
    public function getById(int $id): SupportTicket
    {
        /** @var SupportTicket|null $ticket */
        $ticket = $this->ticketRepository->findById($id);
        if (! $ticket) {
            throw ValidationException::withMessages([
                'ticket_id' => ["تذكرة الدعم الفني برقم [{$id}] غير موجودة."],
            ]);
        }

        $ticket->load(['network', 'pos', 'messages.sentBy', 'latestMessage.sentBy']);

        return $ticket;
    }

    /**
     * جلب تفاصيل تذكرة عبر المعرف الفريد للأعمال (ticket_uuid).
     */
    public function getByUuid(string $uuid): SupportTicket
    {
        $ticket = $this->ticketRepository->findByUuid($uuid);
        if (! $ticket) {
            throw ValidationException::withMessages([
                'ticket_uuid' => ["تذكرة الدعم الفني بالمعرف [{$uuid}] غير موجودة."],
            ]);
        }

        return $ticket;
    }

    /**
     * إنشاء تذكرة دعم فني جديدة مع رسالتها الافتتاحية ذرياً.
     */
    public function create(StoreSupportTicketRequest $request, int $userId): SupportTicket
    {
        return DB::transaction(function () use ($request, $userId) {
            $networkId = $request->getNetworkId();
            $network = $this->networkRepository->findById($networkId);
            if (! $network) {
                throw ValidationException::withMessages([
                    'network_id' => ["شبكة المزود برقم [{$network->network_name}] غير موجودة."],
                ]);
            }
            $network->ensureActive();

            $posId = $request->getPosId();
            if ($posId !== null) {
                $pos = $this->posRepository->findById($posId);
                if (! $pos) {
                    throw ValidationException::withMessages([
                        'pos_id' => ['نقطة البيع المحددة غير موجودة.'],
                    ]);
                }
                $pos->ensureActive();

                $link = $this->networkPosRepository->findByNetworkAndPos($networkId, $posId);
                if ($link === null || ! $link->status) {
                    throw ValidationException::withMessages([
                        'pos_id' => ['نقطة البيع ليست مرتبطة ارتباطاً تجارياً نشطاً بهذه الشبكة.'],
                    ]);
                }
            }

            $actor = User::query()->findOrFail($userId);
            $canCreateForNetwork = $this->tenantPermissions->allowsNetwork(
                $actor,
                $networkId,
                'support_tickets.create'
            );
            $canCreateForPos = $posId !== null && $this->tenantPermissions->allowsPos(
                $actor,
                $posId,
                'support_tickets.create'
            );

            if (! $canCreateForNetwork && ! $canCreateForPos) {
                throw ValidationException::withMessages([
                    'actor' => ['لا تملك صلاحية إنشاء تذكرة دعم لهذه الشبكة أو نقطة البيع.'],
                ]);
            }

            $ticketData = [
                'network_id' => $networkId,
                'pos_id' => $posId,
                'ticket_subject' => $request->getTicketSubject(),
            ];

            /** @var SupportTicket $ticket */
            $ticket = $this->ticketRepository->create($ticketData);

            // إنشاء الرسالة الافتتاحية الأولى إذا تم تمرير نص الرسالة
            $messageBody = $request->getMessageBody();
            if (! empty($messageBody)) {
                $this->messageRepository->create([
                    'ticket_id' => $ticket->ticket_id,
                    'sent_by' => $userId,
                    'message_body' => $messageBody,
                    'sent_at' => now('Asia/Aden'),
                    'is_read' => false,
                ]);
            }

            $ticket->load(['network', 'pos', 'messages.sentBy', 'latestMessage.sentBy']);

            return $ticket;
        });
    }

    /**
     * تحديث بيانات التذكرة (مثل تعديل الموضوع أو الربط).
     */
    public function update(UpdateSupportTicketRequest $request, int $id, int $actorId): SupportTicket
    {
        return DB::transaction(function () use ($request, $id, $actorId) {
            $ticket = $this->getById($id);
            $this->assertTicketPermission($actorId, $ticket, 'support_tickets.update');

            $isReassignment = $request->getNetworkId() !== null
                || $request->has('pos_id');

            if ($isReassignment) {
                $this->assertTicketPermission($actorId, $ticket, 'support_tickets.reassign');
            }

            $updateData = [];

            if ($request->getTicketSubject() !== null) {
                $updateData['ticket_subject'] = $request->getTicketSubject();
            }

            if ($request->getNetworkId() !== null) {
                $network = $this->networkRepository->findById($request->getNetworkId());
                if (! $network) {
                    throw ValidationException::withMessages([
                        'network_id' => ['شبكة المزود المحددة غير موجودة.'],
                    ]);
                }
                $network->ensureActive();
                $updateData['network_id'] = $request->getNetworkId();
            }

            if ($request->has('pos_id')) {
                $posId = $request->getPosId();
                if ($posId !== null) {
                    $pos = $this->posRepository->findById($posId);
                    if (! $pos) {
                        throw ValidationException::withMessages([
                            'pos_id' => ['نقطة البيع المحددة غير موجودة.'],
                        ]);
                    }
                    $pos->ensureActive();
                }
                $updateData['pos_id'] = $posId;
            }

            if ($isReassignment) {
                $targetNetworkId = (int) ($updateData['network_id'] ?? $ticket->network_id);
                $targetPosId = array_key_exists('pos_id', $updateData)
                    ? ($updateData['pos_id'] === null ? null : (int) $updateData['pos_id'])
                    : ($ticket->pos_id === null ? null : (int) $ticket->pos_id);

                if ($targetPosId !== null) {
                    $link = $this->networkPosRepository->findByNetworkAndPos($targetNetworkId, $targetPosId);
                    if ($link === null || ! $link->status) {
                        throw ValidationException::withMessages([
                            'pos_id' => ['نقطة البيع ليست مرتبطة ارتباطاً تجارياً نشطاً بالشبكة المحددة.'],
                        ]);
                    }
                }

                $actor = User::query()->findOrFail($actorId);
                $canReassignToNetwork = $this->tenantPermissions->allowsNetwork(
                    $actor,
                    $targetNetworkId,
                    'support_tickets.reassign'
                );
                $canReassignToPos = $targetPosId !== null && $this->tenantPermissions->allowsPos(
                    $actor,
                    $targetPosId,
                    'support_tickets.reassign'
                );

                if (! $canReassignToNetwork && ! $canReassignToPos) {
                    throw ValidationException::withMessages([
                        'actor' => ['لا تملك صلاحية إعادة إسناد تذكرة الدعم إلى النطاق المستهدف.'],
                    ]);
                }
            }

            if (! empty($updateData)) {
                $this->ticketRepository->update($id, $updateData);
            }

            return $this->getById($id);
        });
    }

    /**
     * إضافة رسالة جديدة إلى تذكرة قائمة.
     */
    public function addMessage(StoreSupportTicketMessageRequest $request, int $ticketId, int $userId): SupportTicketMessage
    {
        return DB::transaction(function () use ($request, $ticketId, $userId) {
            $ticket = $this->getById($ticketId);
            $this->assertTicketPermission($userId, $ticket, 'support_tickets.reply');

            /** @var SupportTicketMessage $message */
            $message = $this->messageRepository->create([
                'ticket_id' => $ticketId,
                'sent_by' => $userId,
                'message_body' => $request->getMessageBody(),
                'sent_at' => now('Asia/Aden'),
                'is_read' => false,
            ]);

            $message->load('sentBy');

            return $message;
        });
    }

    /**
     * جلب كافة رسائل تذكرة معينة مرتبة زمنياً.
     */
    public function getMessages(int $ticketId, int $actorId): Collection
    {
        $ticket = $this->getById($ticketId);
        $this->assertTicketPermission($actorId, $ticket, 'support_tickets.view');

        return $this->messageRepository->getByTicketId($ticketId);
    }

    /**
     * تمييز رسائل التذكرة كمقروءة للمستخدم المستعرض.
     */
    public function markAsRead(int $ticketId, int $readerUserId): int
    {
        $ticket = $this->getById($ticketId);
        $this->assertTicketPermission($readerUserId, $ticket, 'support_tickets.mark_read');

        return DB::transaction(function () use ($ticketId, $readerUserId) {
            return $this->messageRepository->markAsReadForTicket($ticketId, $readerUserId);
        });
    }

    /**
     * حذف تذكرة ناعماً.
     */
    public function delete(int $id): bool
    {
        $this->getById($id);

        return (bool) $this->ticketRepository->delete($id);
    }

    /**
     * استعادة تذكرة محذوفة ناعماً.
     */
    public function restore(int $id): SupportTicket
    {
        $this->ticketRepository->findTrashedById($id);

        $this->ticketRepository->restore($id);

        return $this->getById($id);
    }

    /**
     * الحذف النهائي لتذكرة من قاعدة البيانات.
     */
    public function forceDelete(int $id): bool
    {
        $this->ticketRepository->findTrashedById($id);

        return (bool) $this->ticketRepository->forceDelete($id);
    }

    /**
     * الحذف المجمع للتذاكر ناعماً.
     */
    public function bulkDelete(BatchIdsRequest $request): int
    {
        return $this->ticketRepository->bulkDelete($request->getIds());
    }

    /**
     * الاستعادة المجمعة للتذاكر المحذوفة ناعماً.
     */
    public function bulkRestore(BatchIdsRequest $request): int
    {
        return $this->ticketRepository->bulkRestore($request->getIds());
    }

    /**
     * الحذف النهائي المجمع للتذاكر.
     */
    public function bulkForceDelete(BatchIdsRequest $request): int
    {
        return $this->ticketRepository->bulkForceDelete($request->getIds());
    }

    public function getByIdForActor(int $id, int $actorId, string $permission = 'support_tickets.view'): SupportTicket
    {
        $ticket = $this->getById($id);
        $this->assertTicketPermission($actorId, $ticket, $permission);

        return $ticket;
    }

    /**
     * Restrict list queries to tenant IDs derived from active role grants.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function applyAuthorizedTenantFilters(array $filters, int $actorId, string $permission): array
    {
        $actor = User::query()->findOrFail($actorId);
        $networkIds = $this->tenantPermissions->authorizedNetworkIds($actor, $permission);
        $posIds = $this->tenantPermissions->authorizedPosIds($actor, $permission);

        if ($networkIds === [] && $posIds === []) {
            throw ValidationException::withMessages([
                'actor' => ['لا توجد عضوية نشطة تملك صلاحية الوصول إلى تذاكر الدعم.'],
            ]);
        }

        if (isset($filters['network_id']) && ! in_array((int) $filters['network_id'], $networkIds, true)) {
            throw ValidationException::withMessages([
                'network_id' => ['لا تملك صلاحية عرض تذاكر هذه الشبكة.'],
            ]);
        }

        if (isset($filters['pos_id']) && ! in_array((int) $filters['pos_id'], $posIds, true)) {
            throw ValidationException::withMessages([
                'pos_id' => ['لا تملك صلاحية عرض تذاكر نقطة البيع هذه.'],
            ]);
        }

        $filters['authorized_network_ids'] = $networkIds;
        $filters['authorized_pos_ids'] = $posIds;

        return $filters;
    }

    private function assertTicketPermission(int $actorId, SupportTicket $ticket, string $permission): void
    {
        $actor = User::query()->findOrFail($actorId);

        $networkAllowed = $this->tenantPermissions->allowsNetwork(
            $actor,
            (int) $ticket->network_id,
            $permission
        );
        $posAllowed = $ticket->pos_id !== null && $this->tenantPermissions->allowsPos(
            $actor,
            (int) $ticket->pos_id,
            $permission
        );

        if (! $networkAllowed && ! $posAllowed) {
            throw ValidationException::withMessages([
                'actor' => ['لا تملك الصلاحية اللازمة داخل نطاق تذكرة الدعم المحددة.'],
            ]);
        }
    }
}
