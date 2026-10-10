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
        Schema::create('pos', function (Blueprint $table) {
            $table->id('pos_id');
            $table->string('pos_uuid', 50)->unique('uq_pos_uuid');
            $table->string('pos_name', 50)->unique('uq_pos_name');
            $table->string('pos_profile_logo', 150)->nullable();
            $table->string('pos_holder_name', 50);
            $table->string('pos_phone_number', 20);
            $table->string('pos_email', 50)->nullable();

            $table->foreignId('governorate_id')
                ->constrained('governorates', 'governorate_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('directorate_id')
                ->constrained('directorates', 'directorate_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('pos_address', 150)->nullable();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('rejected_by')
                ->nullable()
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamp('approved_at')->nullable()->index('idx_pos_approved_at');
            $table->timestamp('rejected_at')->nullable()->index('idx_pos_rejected_at');
            $table->string('rejection_reason', 150)->nullable();
            $table->string('status', 20)->default('PENDING')->index('idx_pos_status');

            $table->timestamps();
            $table->softDeletes()->index('idx_pos_deleted_at');

            $table->index('governorate_id', 'idx_pos_governorate_id');
            $table->index('directorate_id', 'idx_pos_directorate_id');
            $table->index('approved_by', 'idx_pos_approved_by');
            $table->index('rejected_by', 'idx_pos_rejected_by');
            $table->index(['governorate_id', 'directorate_id'], 'idx_pos_governorate_directorate');
            $table->index(['status', 'approved_at'], 'idx_pos_status_approved');
            $table->index(['status', 'rejected_at'], 'idx_pos_status_rejected');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos');
    }
};
