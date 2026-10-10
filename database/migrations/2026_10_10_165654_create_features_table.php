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
        Schema::create('features', function (Blueprint $table) {
            $table->id('feature_id');
            $table->string('feature_name', 50)->unique('uq_features_feature_name');
            $table->string('feature_value_type', 20)->default('INTEGER')->index('idx_features_feature_value_type');
            $table->string('feature_value', 50)->nullable();
            $table->string('feature_desc', 150)->nullable();
            $table->boolean('status')->default(true)->index('idx_features_status');

            $table->timestamps();
            $table->softDeletes()->index('idx_features_deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};
