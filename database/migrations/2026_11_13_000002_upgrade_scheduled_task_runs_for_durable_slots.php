<?php

use App\Enums\ScheduledTaskType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_task_runs', function (Blueprint $table): void {
            $table->string('scope_key', 64)->nullable()->after('network_id');
            $table->string('schedule_slot', 32)->default('default')->after('target_date');
            $table->timestamp('started_at')->nullable()->after('sent_at');
            $table->timestamp('finished_at')->nullable()->after('started_at');
            $table->timestamp('locked_until')->nullable()->after('finished_at');
        });

        DB::table('scheduled_task_runs')
            ->select(['id', 'task_type', 'network_id', 'target_date', 'sent_at', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $scopeKey = $row->network_id === null
                        ? 'global'
                        : 'network:'.$row->network_id;
                    $scheduleSlot = $row->task_type === ScheduledTaskType::ALERTS_LOW_BALANCE->value
                        ? '09:00'
                        : 'default';
                    $finishedAt = $row->sent_at ?? $row->updated_at ?? $row->created_at;

                    DB::table('scheduled_task_runs')
                        ->where('id', $row->id)
                        ->update([
                            'scope_key' => $scopeKey,
                            'schedule_slot' => $scheduleSlot,
                            'started_at' => $finishedAt,
                            'finished_at' => $finishedAt,
                        ]);
                }
            });

        // Older global rows could coexist because SQL unique indexes treat NULL
        // network_id values as distinct. Keep every row but isolate duplicate
        // historical occurrences from the new canonical default slot.
        $duplicates = DB::table('scheduled_task_runs')
            ->select(['task_type', 'scope_key', 'target_date', 'schedule_slot'])
            ->groupBy('task_type', 'scope_key', 'target_date', 'schedule_slot')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $rows = DB::table('scheduled_task_runs')
                ->where('task_type', $duplicate->task_type)
                ->where('scope_key', $duplicate->scope_key)
                ->whereDate('target_date', $duplicate->target_date)
                ->where('schedule_slot', $duplicate->schedule_slot)
                ->orderBy('id')
                ->pluck('id');

            // Keep the earliest legacy row on the original slot; retain the
            // remaining rows with deterministic, non-colliding legacy slots.
            foreach ($rows->skip(1) as $id) {
                DB::table('scheduled_task_runs')
                    ->where('id', $id)
                    ->update(['schedule_slot' => 'legacy:'.$id]);
            }
        }

        // The checked-in MySQL schema dump can predate this legacy index even
        // though the original migration declares it. Drop it only when present.
        if (Schema::hasIndex('scheduled_task_runs', 'scheduled_task_network_date_unique')) {
            Schema::table('scheduled_task_runs', function (Blueprint $table): void {
                $table->dropUnique('scheduled_task_network_date_unique');
            });
        }

        Schema::table('scheduled_task_runs', function (Blueprint $table): void {
            $table->string('scope_key', 64)->nullable(false)->change();
            $table->unique(
                ['task_type', 'scope_key', 'target_date', 'schedule_slot'],
                'scheduled_task_scope_slot_unique'
            );
            $table->index(['task_type', 'scope_key', 'target_date'], 'scheduled_task_scope_date_idx');
        });
    }

    public function down(): void
    {
        $duplicates = DB::table('scheduled_task_runs')
            ->select(['task_type', 'network_id', 'target_date'])
            ->whereNotNull('network_id')
            ->groupBy('task_type', 'network_id', 'target_date')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new \RuntimeException(
                'Cannot restore the legacy daily unique key while multiple scheduled slots exist for the same task/network/date. Reconcile these records before rollback.'
            );
        }

        Schema::table('scheduled_task_runs', function (Blueprint $table): void {
            $table->dropUnique('scheduled_task_scope_slot_unique');
            $table->dropIndex('scheduled_task_scope_date_idx');
            $table->dropColumn(['scope_key', 'schedule_slot', 'started_at', 'finished_at', 'locked_until']);
            $table->unique(
                ['task_type', 'network_id', 'target_date'],
                'scheduled_task_network_date_unique'
            );
        });
    }
};
