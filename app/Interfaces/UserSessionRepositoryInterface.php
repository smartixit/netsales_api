<?php

namespace App\Interfaces;

use App\Models\UserSession;
use Illuminate\Database\Eloquent\Collection;

interface UserSessionRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Create an authenticated session.
     */
    public function createSession(
        int $userId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        int $durationHours = 24
    ): UserSession;

    /**
     * Find an active session by ID.
     */
    public function findActiveSession(int $sessionId): ?UserSession;

    /**
     * Revoke a specific session.
     */
    public function revokeSession(int $sessionId): bool;

    /**
     * Revoke all sessions for a user, optionally keeping one active.
     */
    public function revokeAllUserSessions(int $userId, ?int $exceptSessionId = null): int;

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
}
