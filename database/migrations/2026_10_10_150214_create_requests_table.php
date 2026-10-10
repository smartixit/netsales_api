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
        Schema::create('requests', function (Blueprint $table) {
            $table->id('request_id');

            $table->foreignId('network_id')
                ->constrained('networks', 'network_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('pos_id')
                ->constrained('pos', 'pos_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('request_uuid', 50)->unique('uq_requests_request_uuid');
            $table->decimal('request_total_amount', 15, 2)->default(0.00);
            $table->timestamp('request_date')->useCurrent();
            $table->string('status', 20)->default('PENDING')->index('idx_requests_status');

            $table->foreignId('created_by')
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamp('review_date')->nullable();
            $table->string('request_note', 150)->nullable();

            $table->timestamps();
            $table->softDeletes()->index('idx_requests_deleted_at');

            $table->index('network_id', 'idx_requests_network_id');
            $table->index('pos_id', 'idx_requests_pos_id');
            $table->index('created_by', 'idx_requests_created_by');
            $table->index('reviewed_by', 'idx_requests_reviewed_by');
            $table->index(['network_id', 'pos_id'], 'idx_requests_network_pos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
