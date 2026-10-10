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
        Schema::create('networks', function (Blueprint $table) {
            $table->id('network_id');
            $table->string('network_uuid', 50)->unique('uq_networks_network_uuid');
            $table->string('network_name', 50)->unique('uq_networks_network_name');
            $table->string('network_profile_logo', 150)->nullable();
            $table->string('network_holder_name', 50);
            $table->string('network_phone_number', 20);
            $table->string('network_email', 50)->nullable();

            $table->foreignId('governorate_id')
                ->constrained('governorates', 'governorate_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('directorate_id')
                ->constrained('directorates', 'directorate_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('network_address', 150)->nullable();

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

            $table->timestamp('approved_at')->nullable()->index('idx_networks_approved_at');
            $table->timestamp('rejected_at')->nullable()->index('idx_networks_rejected_at');
            $table->string('rejection_reason', 150)->nullable();
            $table->string('status', 20)->default('PENDING')->index('idx_networks_status');

            $table->timestamps();
            $table->softDeletes()->index('idx_networks_deleted_at');

            $table->index('governorate_id', 'idx_networks_governorate_id');
            $table->index('directorate_id', 'idx_networks_directorate_id');
            $table->index('approved_by', 'idx_networks_approved_by');
            $table->index('rejected_by', 'idx_networks_rejected_by');
            $table->index(['governorate_id', 'directorate_id'], 'idx_networks_governorate_directorate');
            $table->index(['status', 'approved_at'], 'idx_networks_approved');
            $table->index(['status', 'rejected_at'], 'idx_networks_rejected');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('networks');
    }
};
