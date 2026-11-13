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

                $netMovementMinor = 0;
                foreach ($logs as $log) {
                    $amountMinor = $this->decimalToMinorUnits($log->log_amount);
                    $movementMinor = match ($log->{$operationColumn}) {
                        $depositValue => $amountMinor,
                        $withdrawValue => -$amountMinor,
                        default => 0,
                    };
                    $netMovementMinor = $this->addMinorUnits($netMovementMinor, $movementMinor);
                }

                $walletBalanceMinor = $this->decimalToMinorUnits($wallet->wallet_balance);
                $openingBalanceMinor = $this->addMinorUnits($walletBalanceMinor, -$netMovementMinor);
                $openingBalance = $this->minorUnitsToDecimal($openingBalanceMinor);

                DB::table($walletTable)
                    ->where('wallet_id', $wallet->wallet_id)
                    ->update([
                        'opening_balance' => $openingBalance,
                        'opening_balance_source' => 'BACKFILLED',
                        'currency_code' => 'YER',
                    ]);

                $runningBalanceMinor = $openingBalanceMinor;
                foreach ($logs as $log) {
                    $before = $this->minorUnitsToDecimal($runningBalanceMinor);
                    $amountMinor = $this->decimalToMinorUnits($log->log_amount);
                    $runningBalanceMinor = $this->addMinorUnits(
                        $runningBalanceMinor,
                        match ($log->{$operationColumn}) {
                            $depositValue => $amountMinor,
                            $withdrawValue => -$amountMinor,
                            default => 0,
                        }
                    );
                    $after = $this->minorUnitsToDecimal($runningBalanceMinor);

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

    private function decimalToMinorUnits(string|int|float|null $value): int
    {
        $decimal = trim((string) ($value ?? '0'));
        if (preg_match('/^([+-]?)(\\d+)(?:\\.(\\d+))?$/D', $decimal, $matches) !== 1) {
            throw new \\InvalidArgumentException('Invalid wallet ledger amount.');
        }

        $fraction = $matches[3] ?? '';
        if (strlen($fraction) > 2 && trim(substr($fraction, 2), '0') !== '') {
            throw new \\InvalidArgumentException('Wallet ledger amounts support two decimal places.');
        }

        $whole = ltrim($matches[2], '0') ?: '0';
        $maximumWhole = intdiv(PHP_INT_MAX - 99, 100);
        if (strlen($whole) > strlen((string) $maximumWhole)
            || (strlen($whole) === strlen((string) $maximumWhole) && strcmp($whole, (string) $maximumWhole) > 0)) {
            throw new \\OverflowException('Wallet ledger amount exceeds the supported integer range.');
        }

        $minorUnits = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
        return ($matches[1] ?? '') === '-' ? -$minorUnits : $minorUnits;
    }

    private function minorUnitsToDecimal(int $minorUnits): string
    {
        $negative = $minorUnits < 0;
        $absolute = abs($minorUnits);

        return ($negative ? '-' : '')
            .intdiv($absolute, 100)
            .'.'
            .str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    private function addMinorUnits(int $left, int $right): int
    {
        if (($right > 0 && $left > PHP_INT_MAX - $right)
            || ($right < 0 && $left < PHP_INT_MIN - $right)) {
            throw new \\OverflowException('Wallet ledger total exceeds the supported integer range.');
        }

        return $left + $right;
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
