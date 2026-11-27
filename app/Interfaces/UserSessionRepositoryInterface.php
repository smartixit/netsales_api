<?php

namespace App\Interfaces;

use App\Enums\UserSessionStatus;
use App\Models\UserSession;
use Illuminate\Database\Eloquent\Collection;

interface UserSessionRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Create an authenticated session.
     */
    public function createSession(
        int $userId,
        string $deviceUuid,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?int $durationHours = null
    ): UserSession;

    /**
     * Find an active session by token or ID.
     */
    public function findActiveSession(string|int $sessionId): ?UserSession;

    /**
     * Find a session by token or ID (active or inactive).
     */
    public function findByTokenOrId(string|int $sessionId, bool $lock = false): ?UserSession;

    /**
     * Revoke a specific session.
     */
    public function revokeSession(int $sessionId): bool;

    /**
     * Revoke all sessions for a user, optionally keeping one active.
     */
    public function revokeAllUserSessions(int $userId, ?int $exceptSessionId = null): int;

    /**
     * Revoke all active sessions bound to a specific device UUID.
     */
    public function revokeSessionsByDeviceUuid(string $deviceUuid): int;

    /**
     * Touch session activity timestamp.
     */
    public function touchActivity(int $sessionId): void;

    /**
     * Get active sessions for a user.
     *
     * @return Collection<int, UserSession>
     */
    public function getActiveSessionsForUser(int $userId): Collection;

    /**
     * Get sessions for a user with optional status and search filters.
     *
     * @return Collection<int, UserSession>
     */
    public function getSessionsForUser(
        int $userId,
        ?UserSessionStatus $status = null,
        ?string $search = null
    ): Collection;

    /**
     * Terminate a specific session owned by the given user.
     */
    public function terminateUserSession(int $userId, int $sessionId): bool;

    /**
     * Delete expired or revoked sessions older than cutoff.
     */
    public function deleteExpiredOrRevoked(\DateTimeInterface $cutoff): int;
}
