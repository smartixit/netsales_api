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
        Schema::create('subscription_plan_features', function (Blueprint $table) {
            $table->id('subscription_plan_feature_id');

            $table->foreignId('plan_id')
                ->constrained('subscription_plans', 'plan_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('feature_id')
                ->constrained('features', 'feature_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('feature_value', 50);
            $table->boolean('status')->default(true)->index('idx_subscription_plan_features_status');

            $table->foreignId('created_by')
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes()->index('idx_subscription_plan_features_deleted_at');

            $table->index('plan_id', 'idx_subscription_plan_features_plan_id');
            $table->index('feature_id', 'idx_subscription_plan_features_feature_id');
            $table->index('created_by', 'idx_subscription_plan_features_created_by');
            $table->index(['plan_id', 'feature_id'], 'idx_subscription_plan_features_plan_feature');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_features');
    }
};
