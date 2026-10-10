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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id('expense_id');

            $table->foreignId('network_id')
                ->constrained('networks', 'network_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('expense_uuid', 50)->unique('uq_expenses_expense_uuid');
            $table->decimal('expense_total_amount', 15, 2)->default(0.00);
            $table->timestamp('expense_date')->useCurrent();

            $table->foreignId('created_by')
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes()->index('idx_expenses_deleted_at');

            $table->index('network_id', 'idx_expenses_network_id');
            $table->index('created_by', 'idx_expenses_created_by');
            $table->index(['network_id', 'expense_date'], 'idx_expenses_network_date');
            $table->index(['network_id', 'created_by'], 'idx_expenses_network_created_by');
            $table->index(['network_id', 'expense_date', 'created_by'], 'idx_expenses_network_date_created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
