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
        Schema::create('modules', function (Blueprint $table) {
            $table->id('module_id');
            $table->string('module_key', 50)->unique('uq_modules_module_key');
            $table->string('module_name', 50);
            $table->string('module_icon', 50)->nullable();
            $table->string('module_desc', 150)->nullable();
            $table->boolean('status')->default(true)->index('idx_modules_status');

            $table->timestamps();
            $table->softDeletes()->index('idx_modules_deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
