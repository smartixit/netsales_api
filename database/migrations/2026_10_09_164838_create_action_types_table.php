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
        Schema::create('action_types', function (Blueprint $table) {
            $table->id('action_type_id');
            $table->string('action_type_key', 50)->unique('uq_action_types_action_type_key');
            $table->string('action_type_name', 50);
            $table->boolean('status')->default(true)->index('idx_action_types_status');

            $table->timestamps();
            $table->softDeletes()->index('idx_action_types_deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('action_types');
    }
};
