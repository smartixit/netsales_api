<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove legacy subscription controls from the old global settings store.
     *
     * Trial eligibility, duration, billing access, and dunning behavior are
     * now owned by versioned Billing Policy records, never system_settings.
     */
    public function up(): void
    {
        DB::table('system_settings')
            ->where('setting_category', 'SUBSCRIPTION')
            ->delete();
    }

    /**
     * The deleted settings are obsolete and intentionally are not restored.
     */
    public function down(): void
    {
        // No-op: removed legacy settings must not be recreated on rollback.
    }
};
