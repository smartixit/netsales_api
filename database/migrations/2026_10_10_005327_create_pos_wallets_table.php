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
        Schema::create('pos_wallets', function (Blueprint $table) {
            $table->id('wallet_id');

            $table->foreignId('network_id')
                ->constrained('networks', 'network_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('pos_id')
                ->constrained('pos', 'pos_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('wallet_uuid', 50)->unique('uq_pos_wallets_wallet_uuid');
            $table->decimal('wallet_balance', 15, 2)->default(0.00);
            $table->boolean('status')->default(true)->index('idx_pos_wallets_status');

            $table->timestamps();
            $table->softDeletes()->index('idx_pos_wallets_deleted_at');

            $table->unique(['network_id', 'pos_id'], 'uq_pos_wallets_network_pos');
            $table->index('network_id', 'idx_pos_wallets_network_id');
            $table->index('pos_id', 'idx_pos_wallets_pos_id');
            $table->index(['network_id', 'status'], 'idx_pos_wallets_network_status');
            $table->index(['pos_id', 'status'], 'idx_pos_wallets_pos_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_wallets');
    }
};
