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
        // 1. Create wallet_transfers table
        Schema::create('wallet_transfers', function (Blueprint $table) {
            $table->id('transfer_id');
            $table->string('transfer_uuid', 64)->unique('uq_transfers_uuid');
            $table->unsignedBigInteger('network_wallet_id');
            $table->unsignedBigInteger('pos_wallet_id');
            $table->decimal('amount', 15, 2);
            $table->enum('direction', ['NET_TO_POS', 'POS_TO_NET']);
            $table->unsignedBigInteger('original_transfer_id')->nullable();

            // Generated columns for strict reversal integrity
            $table->unsignedTinyInteger('is_reversal')
                ->storedAs('CASE WHEN original_transfer_id IS NOT NULL THEN 1 ELSE 0 END');
            $table->unsignedTinyInteger('target_is_reversal')
                ->storedAs('CASE WHEN original_transfer_id IS NOT NULL THEN 0 ELSE NULL END');

            $table->enum('status', ['COMPLETED', 'REVERSED'])->default('COMPLETED');
            $table->string('statement', 255)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            // Unique constraints for composite foreign keys
            $table->unique(['transfer_id', 'network_wallet_id'], 'uq_transfers_net_wallet');
            $table->unique(['transfer_id', 'pos_wallet_id'], 'uq_transfers_pos_wallet');
            $table->unique(['transfer_id', 'is_reversal'], 'uq_transfers_is_reversal');
            $table->unique('original_transfer_id', 'uq_original_transfer');

            // Foreign keys
            $table->foreign('network_wallet_id')->references('wallet_id')->on('network_wallets')->onDelete('restrict');
            $table->foreign('pos_wallet_id')->references('wallet_id')->on('pos_wallets')->onDelete('restrict');
            $table->foreign('created_by')->references('user_id')->on('users')->onDelete('restrict');

            // Reversal constraint: original_transfer_id MUST reference a transfer where is_reversal = 0
            $table->foreign(['original_transfer_id', 'target_is_reversal'])
                ->references(['transfer_id', 'is_reversal'])
                ->on('wallet_transfers')
                ->onDelete('restrict');
        });

        // 2. Add transfer_id to network_wallet_logs with composite FK
        Schema::table('network_wallet_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('transfer_id')->nullable()->after('wallet_id');

            $table->foreign(['transfer_id', 'wallet_id'], 'fk_net_logs_transfer_identity')
                ->references(['transfer_id', 'network_wallet_id'])
                ->on('wallet_transfers')
                ->onDelete('restrict');
        });

        // 3. Add transfer_id to pos_wallet_logs with composite FK
        Schema::table('pos_wallet_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('transfer_id')->nullable()->after('wallet_id');

            $table->foreign(['transfer_id', 'wallet_id'], 'fk_pos_logs_transfer_identity')
                ->references(['transfer_id', 'pos_wallet_id'])
                ->on('wallet_transfers')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_wallet_logs', function (Blueprint $table) {
            $table->dropForeign('fk_pos_logs_transfer_identity');
            $table->dropColumn('transfer_id');
        });

        Schema::table('network_wallet_logs', function (Blueprint $table) {
            $table->dropForeign('fk_net_logs_transfer_identity');
            $table->dropColumn('transfer_id');
        });

        Schema::dropIfExists('wallet_transfers');
    }
};
