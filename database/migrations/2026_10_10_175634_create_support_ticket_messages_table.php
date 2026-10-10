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
        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id('message_id');

            $table->foreignId('ticket_id')
                ->constrained('support_tickets', 'ticket_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('sent_by')
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->text('message_body');
            $table->timestamp('sent_at')->useCurrent();
            $table->boolean('is_read')->default(false)->index('idx_support_ticket_messages_is_read');
            $table->timestamp('read_at')->nullable();

            $table->timestamps();
            $table->softDeletes()->index('idx_support_ticket_messages_deleted_at');

            $table->index('ticket_id', 'idx_support_ticket_messages_ticket_id');
            $table->index('sent_by', 'idx_support_ticket_messages_sent_by');
            $table->index(['ticket_id', 'is_read'], 'idx_support_ticket_messages_ticket_is_read');
            $table->index(['ticket_id', 'is_read', 'sent_at'], 'idx_support_ticket_messages_ticket_is_read_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
    }
};
