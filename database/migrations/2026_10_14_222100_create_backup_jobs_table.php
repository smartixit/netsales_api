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
        Schema::create('backup_jobs', function (Blueprint $table) {
            $table->unsignedBigInteger('backup_job_id')->primary()->autoIncrement();
            $table->char('job_uuid', 36)->unique();
            $table->string('backup_type', 20)->default('manual'); // manual | auto
            $table->string('status', 20)->default('queued');     // queued | running | success | failed
            $table->string('stage', 32)->nullable();             // dump | verify | archive | upload | retention | finalize
            $table->string('file_name', 255)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->char('archive_sha256', 64)->nullable();
            $table->string('drive_file_id', 255)->nullable();
            $table->string('drive_web_view_link', 512)->nullable();
            $table->string('drive_folder_id', 255)->nullable();
            $table->json('manifest_summary')->nullable();
            $table->json('extra')->nullable();                   // upload session, retention details, progress
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at'], 'backup_jobs_status_created_idx');
            $table->index(['backup_type', 'created_at'], 'backup_jobs_type_created_idx');
            $table->index('drive_file_id', 'backup_jobs_drive_file_idx');

            $table->foreign('created_by', 'backup_jobs_created_by_foreign')
                ->references('user_id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_jobs');
    }
};
