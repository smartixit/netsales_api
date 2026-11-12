<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_report_exports', function (Blueprint $table): void {
            $table->id('report_export_id');
            $table->uuid('export_uuid')->unique();
            $table->foreignId('network_id')
                ->constrained('networks', 'network_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('pos_id')
                ->nullable()
                ->constrained('pos', 'pos_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('requested_by')
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('report_type', 48);
            $table->json('filters');
            $table->string('status', 24)->default('QUEUED');
            $table->string('artifact_path', 500)->nullable();
            $table->char('artifact_sha256', 64)->nullable();
            $table->unsignedBigInteger('artifact_size')->nullable();
            $table->unsignedInteger('record_count')->default(0);
            $table->string('failure_code', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['network_id', 'status', 'created_at'], 'operational_report_exports_network_status_idx');
            $table->index(['requested_by', 'created_at'], 'operational_report_exports_requester_idx');
            $table->index(['status', 'expires_at'], 'operational_report_exports_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_report_exports');
    }
};
