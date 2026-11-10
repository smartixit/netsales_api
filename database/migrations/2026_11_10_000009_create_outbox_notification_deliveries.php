<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_notification_deliveries', function (Blueprint $table): void {
            $table->id('delivery_id');
            $table->string('message_uuid', 64)->unique();
            $table->unsignedBigInteger('notification_id')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_notification_deliveries');
    }
};
