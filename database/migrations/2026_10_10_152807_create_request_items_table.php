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
        Schema::create('request_items', function (Blueprint $table) {
            $table->id('item_id');

            $table->foreignId('request_id')
                ->constrained('requests', 'request_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('package_id')
                ->constrained('voucher_packages', 'package_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->integer('item_voucher_count')->default(0);

            $table->timestamps();
            $table->softDeletes()->index('idx_request_items_deleted_at');

            $table->index('request_id', 'idx_request_items_request_id');
            $table->index('package_id', 'idx_request_items_package_id');
            $table->index(['request_id', 'package_id'], 'idx_request_items_request_package');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_items');
    }
};
