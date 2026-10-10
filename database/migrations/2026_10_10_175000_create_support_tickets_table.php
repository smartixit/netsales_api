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
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id('ticket_id');

            $table->foreignId('network_id')
                ->constrained('networks', 'network_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('pos_id')
                ->constrained('pos', 'pos_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('ticket_uuid', 50)->unique('uq_support_tickets_ticket_uuid');
            $table->string('ticket_subject', 255);

            $table->timestamps();
            $table->softDeletes()->index('idx_support_tickets_deleted_at');

            $table->index('network_id', 'idx_support_tickets_network_id');
            $table->index('pos_id', 'idx_support_tickets_pos_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
