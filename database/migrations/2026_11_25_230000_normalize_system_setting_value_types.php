<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')
            ->whereIn('setting_value_type', ['string', 'text', 'integer', 'boolean', 'decimal', 'json', 'datetime'])
            ->update([
                'setting_value_type' => DB::raw("CASE UPPER(setting_value_type)
                    WHEN 'STRING' THEN 'TEXT'
                    WHEN 'TEXT' THEN 'TEXT'
                    WHEN 'INTEGER' THEN 'INTEGER'
                    WHEN 'BOOLEAN' THEN 'BOOLEAN'
                    WHEN 'DECIMAL' THEN 'DECIMAL'
                    WHEN 'JSON' THEN 'JSON'
                    WHEN 'DATETIME' THEN 'DATETIME'
                    ELSE setting_value_type
                END"),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Canonical enum values are intentionally not reverted to invalid legacy strings.
    }
};
