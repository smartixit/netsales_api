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
        Schema::create('network_pos', function (Blueprint $table) {
            $table->id('network_pos_id');

            $table->foreignId('network_id')
                ->constrained('networks', 'network_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('pos_id')
                ->constrained('pos', 'pos_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->boolean('status')->default(true)->index('idx_network_pos_status');

            $table->timestamps();

            $table->unique(['network_id', 'pos_id'], 'uq_network_pos_network_pos');
            $table->index('network_id', 'idx_network_network_pos_id');
            $table->index('pos_id', 'idx_network_pos_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('network_pos');
    }
};
