<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_wallets', function (Blueprint $table): void {
            $table->decimal('opening_balance', 15, 2)->default(0)->after('wallet_balance');
            $table->string('opening_balance_source', 20)->default('BACKFILLED')->after('opening_balance');
            $table->char('currency_code', 3)->default('YER')->after('opening_balance');
        });

        Schema::table('pos_wallets', function (Blueprint $table): void {
            $table->decimal('opening_balance', 15, 2)->default(0)->after('wallet_balance');
            $table->string('opening_balance_source', 20)->default('BACKFILLED')->after('opening_balance');
            $table->char('currency_code', 3)->default('YER')->after('opening_balance_source');
        });

        Schema::table('network_wallet_logs', function (Blueprint $table): void {
            $table->decimal('balance_before', 15, 2)->default(0)->after('log_amount');
            $table->decimal('balance_after', 15, 2)->default(0)->after('balance_before');
            $table->string('balance_snapshot_source', 20)->default('BACKFILLED')->after('balance_after');
            $table->char('currency_code', 3)->default('YER')->after('balance_after');
            $table->index(['wallet_id', 'created_at', 'log_id'], 'idx_network_wallet_posting_sequence');
            $table->index(['wallet_id', 'log_date', 'log_id'], 'idx_network_wallet_ledger_sequence');
        });

        Schema::table('pos_wallet_logs', function (Blueprint $table): void {
            $table->decimal('balance_before', 15, 2)->default(0)->after('log_amount');
            $table->decimal('balance_after', 15, 2)->default(0)->after('balance_before');
            $table->string('balance_snapshot_source', 20)->default('BACKFILLED')->after('balance_after');
            $table->char('currency_code', 3)->default('YER')->after('balance_snapshot_source');
            $table->index(['wallet_id', 'created_at', 'log_id'], 'idx_pos_wallet_posting_sequence');
            $table->index(['wallet_id', 'log_date', 'log_id'], 'idx_pos_wallet_ledger_sequence');
        });

        DB::transaction(function (): void {
            $this->backfillLedger(
                'network_wallets',
                'network_wallet_logs',
                'log_operation',
                'DEPOSIT',
                'WITHDRAW'
            );
            $this->backfillLedger(
                'pos_wallets',
                'pos_wallet_logs',
                'log_operation_type',
                'DEPOSIT',
                'WITHDRAW'
            );
        });
    }

    private function backfillLedger(
        string $walletTable,
        string $logTable,
        string $operationColumn,
        string $depositValue,
        string $withdrawValue
    ): void {
        DB::table($walletTable)->orderBy('wallet_id')->chunkById(200, function ($wallets) use (
            $walletTable,
            $logTable,
            $operationColumn,
            $depositValue,
            $withdrawValue
        ): void {
            foreach ($wallets as $wallet) {
                $logs = DB::table($logTable)
                    ->where('wallet_id', $wallet->wallet_id)
                    ->orderBy('log_id')
                    ->get(['log_id', 'log_amount', $operationColumn]);

                $netMovement = 0.0;
                foreach ($logs as $log) {
                    $amount = (float) $log->log_amount;
                    $netMovement += match ($log->{$operationColumn}) {
                        $depositValue => $amount,
                        $withdrawValue => -$amount,
                        default => 0.0,
                    };
                }

                // Preserve the live wallet balance as the accounting truth. The
                // residual becomes the opening balance before the first ledger row.
                $openingBalance = round((float) $wallet->wallet_balance - $netMovement, 2);
                DB::table($walletTable)
                    ->where('wallet_id', $wallet->wallet_id)
                    ->update([
                        'opening_balance' => $openingBalance,
                        'opening_balance_source' => 'BACKFILLED',
                        'currency_code' => 'YER',
                    ]);

                $runningBalance = $openingBalance;
                foreach ($logs as $log) {
                    $before = round($runningBalance, 2);
                    $amount = (float) $log->log_amount;
                    $runningBalance = match ($log->{$operationColumn}) {
                        $depositValue => $runningBalance + $amount,
                        $withdrawValue => $runningBalance - $amount,
                        default => $runningBalance,
                    };
                    $after = round($runningBalance, 2);

                    DB::table($logTable)
                        ->where('log_id', $log->log_id)
                        ->update([
                            'balance_before' => $before,
                            'balance_after' => $after,
                            'balance_snapshot_source' => 'BACKFILLED',
                            'currency_code' => 'YER',
                        ]);
                }
            }
        }, 'wallet_id');
    }

    public function down(): void
    {
        Schema::table('network_wallet_logs', function (Blueprint $table): void {
            $table->dropIndex('idx_network_wallet_ledger_sequence');
            $table->dropIndex('idx_network_wallet_posting_sequence');
            $table->dropColumn(['balance_before', 'balance_after', 'balance_snapshot_source', 'currency_code']);
        });
        Schema::table('pos_wallet_logs', function (Blueprint $table): void {
            $table->dropIndex('idx_pos_wallet_ledger_sequence');
            $table->dropIndex('idx_pos_wallet_posting_sequence');
            $table->dropColumn(['balance_before', 'balance_after', 'balance_snapshot_source', 'currency_code']);
        });
        Schema::table('network_wallets', function (Blueprint $table): void {
            $table->dropColumn(['opening_balance', 'opening_balance_source', 'currency_code']);
        });
        Schema::table('pos_wallets', function (Blueprint $table): void {
            $table->dropColumn(['opening_balance', 'opening_balance_source', 'currency_code']);
        });
    }
};
