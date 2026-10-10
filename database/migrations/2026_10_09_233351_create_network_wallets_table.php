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
        Schema::create('network_wallets', function (Blueprint $table) {
            $table->id('wallet_id');

            $table->foreignId('network_id')
                ->constrained('networks', 'network_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('wallet_uuid', 50)->unique('uq_network_wallets_wallet_uuid');
            $table->decimal('wallet_balance', 15, 2)->default(0.00);
            $table->boolean('status')->default(true)->index('idx_network_wallets_status');

            $table->timestamps();
            $table->softDeletes()->index('idx_network_wallets_deleted_at');

            $table->unique('network_id', 'uq_network_wallets_network_id');
            $table->index('network_id', 'idx_network_wallets_network_id');
            $table->index(['network_id', 'status'], 'idx_network_wallets_network_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('network_wallets');
    }
};
