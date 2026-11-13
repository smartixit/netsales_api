<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_task_runs', function (Blueprint $table) {
            $table->dropUnique('scheduled_task_network_date_unique');
            $table->string('scope_key', 32)->default('global')->after('network_id');
            $table->string('schedule_slot', 32)->default('default')->after('target_date');
            $table->timestamp('started_at')->nullable()->after('sent_at');
            $table->timestamp('finished_at')->nullable()->after('started_at');
            $table->timestamp('locked_until')->nullable()->after('finished_at');
        });

        DB::table('scheduled_task_runs')
            ->whereNotNull('network_id')
            ->orderBy('id')
            ->chunkById(500, function ($runs): void {
                foreach ($runs as $run) {
                    DB::table('scheduled_task_runs')
                        ->where('id', $run->id)
                        ->update(['scope_key' => 'network:'.$run->network_id]);
                }
            });

        // Preserve historical rows while assigning distinct identities to any old global duplicates.
        $duplicates = DB::table('scheduled_task_runs')
            ->select('task_type', 'scope_key', 'target_date', 'schedule_slot')
            ->groupBy('task_type', 'scope_key', 'target_date', 'schedule_slot')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $ids = DB::table('scheduled_task_runs')
                ->where('task_type', $duplicate->task_type)
                ->where('scope_key', $duplicate->scope_key)
                ->where('target_date', $duplicate->target_date)
                ->where('schedule_slot', $duplicate->schedule_slot)
                ->orderBy('id')
                ->pluck('id');

            $ids->shift();

            foreach ($ids as $id) {
                DB::table('scheduled_task_runs')
                    ->where('id', $id)
                    ->update(['schedule_slot' => 'legacy-'.$id]);
            }
        }

        Schema::table('scheduled_task_runs', function (Blueprint $table) {
            $table->unique(
                ['task_type', 'scope_key', 'target_date', 'schedule_slot'],
                'scheduled_task_scope_slot_unique'
            );
            $table->index(['task_type', 'schedule_slot'], 'scheduled_task_type_slot_idx');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_task_runs', function (Blueprint $table) {
            $table->dropUnique('scheduled_task_scope_slot_unique');
            $table->dropIndex('scheduled_task_type_slot_idx');
            $table->dropColumn([
                'scope_key',
                'schedule_slot',
                'started_at',
                'finished_at',
                'locked_until',
            ]);
            $table->unique(
                ['task_type', 'network_id', 'target_date'],
                'scheduled_task_network_date_unique'
            );
        });
    }
};
