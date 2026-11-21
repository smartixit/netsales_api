<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Untargeted invitations cannot satisfy the mandatory network-to-POS contract.
        DB::table('network_pos_linking_challenges')
            ->whereNull('pos_id')
            ->delete();

        Schema::table('network_pos_linking_challenges', function (Blueprint $table) {
            // The original FK used ON DELETE SET NULL, which conflicts with NOT NULL.
            $table->dropForeign(['pos_id']);
            $table->unsignedBigInteger('pos_id')->nullable(false)->change();
            $table->foreign('pos_id')
                ->references('pos_id')
                ->on('pos')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('network_pos_linking_challenges', function (Blueprint $table) {
            $table->dropForeign(['pos_id']);
            $table->unsignedBigInteger('pos_id')->nullable()->change();
            $table->foreign('pos_id')
                ->references('pos_id')
                ->on('pos')
                ->nullOnDelete();
        });
    }
};
