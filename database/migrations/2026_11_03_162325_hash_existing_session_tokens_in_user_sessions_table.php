<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Explicitly revoke any historical unhashed development sessions to enforce SHA-256 moving forward
        DB::table('user_sessions')
            ->whereNotNull('session_token')
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now('Asia/Aden'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversal needed for security revocation of stale development tokens
    }
};
