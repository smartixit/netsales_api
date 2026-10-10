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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id('notification_id');

            $table->foreignId('user_id')
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('notification_type', 50)->default('SYSTEM')->index('idx_notifications_notification_type');
            $table->string('notification_title', 255);
            $table->text('notification_details');
            $table->text('notification_image')->nullable();
            $table->boolean('is_read')->default(false)->index('idx_notifications_is_read');
            $table->timestamp('read_at')->nullable();

            $table->timestamps();
            $table->softDeletes()->index('idx_notifications_deleted_at');

            $table->index('user_id', 'idx_notifications_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
