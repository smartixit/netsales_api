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
        Schema::create('expense_items', function (Blueprint $table) {
            $table->id('item_id');

            $table->foreignId('expense_id')
                ->constrained('expenses', 'expense_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->decimal('item_amount', 15, 2)->default(0.00);
            $table->string('item_statement', 150);

            $table->timestamps();
            $table->softDeletes()->index('idx_expense_items_deleted_at');

            $table->index('expense_id', 'idx_expense_items_expense_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_items');
    }
};
