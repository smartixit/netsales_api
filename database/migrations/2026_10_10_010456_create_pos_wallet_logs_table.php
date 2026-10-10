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
        Schema::create('pos_wallet_logs', function (Blueprint $table) {
            $table->id('log_id');

            $table->foreignId('wallet_id')
                ->constrained('pos_wallets', 'wallet_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('log_operation', 50)->default('WITHDRAW')->index('idx_pos_wallet_logs_log_operation'); // DEPOSIT, WITHDRAW
            $table->decimal('log_amount', 15, 2)->default(0.00);
            $table->text('log_statement');
            $table->timestamp('log_date')->useCurrent();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes()->index('idx_pos_wallet_logs_deleted_at');

            $table->index('wallet_id', 'idx_pos_wallet_logs_wallet_id');
            $table->index('created_by', 'idx_pos_wallet_logs_created_by');
            $table->index(['wallet_id', 'log_date'], 'idx_pos_wallet_logs_wallet_date');
            $table->index(['wallet_id', 'log_operation'], 'idx_pos_wallet_logs_wallet_operation');
            $table->index(['wallet_id', 'created_by'], 'idx_pos_wallet_logs_wallet_created_by');
            $table->index(['wallet_id', 'log_date', 'created_by'], 'idx_pos_wallet_logs_wallet_date_created_by');
            $table->index(['wallet_id', 'log_date', 'log_operation'], 'idx_pos_wallet_logs_wallet_date_operation');
            $table->index(['wallet_id', 'log_operation', 'created_by'], 'idx_pos_wallet_logs_wallet_operation_created_by');
            $table->index(['wallet_id', 'log_operation', 'log_date'], 'idx_pos_wallet_logs_wallet_operation_date');
            $table->index(['wallet_id', 'log_operation', 'log_date', 'created_by'], 'idx_pos_wallet_logs_wallet_operation_date_created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_wallet_logs');
    }
};
