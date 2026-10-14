<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->string('session_token', 64)->nullable()->unique('idx_user_sessions_session_token')->after('session_id');
            $table->string('device_uuid', 100)->nullable()->after('user_agent');

            $table->index('device_uuid', 'idx_user_sessions_device_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_user_sessions_device_uuid');
            $table->dropUnique('idx_user_sessions_session_token');
            $table->dropColumn(['session_token', 'device_uuid']);
        });
    }
};
