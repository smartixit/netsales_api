<?php

namespace App\Services;

use App\Models\UserSession;
use Illuminate\Support\Carbon;

class SessionValidatorService
{
    /**
     * Parse and validate a session ID.
     *
     * @return UserSession|null Returns the valid session or null if invalid/expired.
     */
    public function validateSession(?string $sessionId): ?UserSession
    {
        if (empty($sessionId)) {
            return null;
        }

        $session = UserSession::where('session_id', $sessionId)
            ->where('expires_at', '>', Carbon::now('Asia/Aden'))
            ->first();

        if ($session) {
            // Extend last activity silently
            $session->update(['last_activity_at' => Carbon::now('Asia/Aden')]);

            return $session;
        }

        return null;
    }

    /**
     * Terminate / Expire a session explicitly.
     */
    public function invalidateSession(string $sessionId): bool
    {
        $session = UserSession::where('session_id', $sessionId)->first();
        if ($session) {
            return $session->update(['expires_at' => Carbon::now('Asia/Aden')]);
        }

        return false;
    }
}
