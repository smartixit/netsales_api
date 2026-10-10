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
        Schema::create('expense_attachments', function (Blueprint $table) {
            $table->id('attachment_id');

            $table->foreignId('expense_id')
                ->constrained('expenses', 'expense_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->text('attachment_path');
            $table->string('attachment_name', 100);
            $table->string('attachment_mime', 20);
            $table->integer('attachment_size');

            $table->timestamps();

            $table->index('expense_id', 'idx_expense_attachments_expense_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_attachments');
    }
};
