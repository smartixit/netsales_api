<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_runs', function (Blueprint $table): void {
            $table->bigIncrements('report_run_id');
            $table->uuid('report_run_uuid')->unique('uq_report_run_uuid');
            $table->string('scope_type', 16);
            $table->unsignedBigInteger('network_id')->nullable();
            $table->unsignedBigInteger('requested_by');
            $table->string('permission_key', 120);
            $table->string('report_key', 120);
            $table->string('definition_version', 32);
            $table->string('status', 20)->default('QUEUED');
            $table->json('filters');
            $table->string('artifact_format', 16)->default('JSON');
            $table->string('artifact_path', 512)->nullable();
            $table->char('artifact_sha256', 64)->nullable();
            $table->unsignedBigInteger('artifact_bytes')->default(0);
            $table->unsignedInteger('records_count')->default(0);
            $table->boolean('is_truncated')->default(false);
            $table->json('result_metadata')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at')->useCurrent();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['scope_type', 'network_id', 'report_run_id'], 'idx_report_runs_scope');
            $table->index(['requested_by', 'created_at'], 'idx_report_runs_requester');
            $table->index(['status', 'queued_at'], 'idx_report_runs_queue');
            $table->index(['report_key', 'status'], 'idx_report_runs_key_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_runs');
    }
};
