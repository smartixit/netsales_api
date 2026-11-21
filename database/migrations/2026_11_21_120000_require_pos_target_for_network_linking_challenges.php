<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Old untargeted invitations cannot satisfy the new relationship contract.
        DB::table('network_pos_linking_challenges')
            ->whereNull('pos_id')
            ->delete();

        Schema::table('network_pos_linking_challenges', function (Blueprint $table) {
            $table->foreignId('pos_id')
                ->nullable(false)
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('network_pos_linking_challenges', function (Blueprint $table) {
            $table->foreignId('pos_id')
                ->nullable()
                ->change();
        });
    }
};
