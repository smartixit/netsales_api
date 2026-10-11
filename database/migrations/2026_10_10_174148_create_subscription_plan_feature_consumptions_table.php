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
        Schema::create('subscription_plan_feature_consumptions', function (Blueprint $table) {
            $table->id('consumption_id');

            $table->foreignId('subscription_id')
                ->constrained('provider_subscriptions', 'subscription_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('feature_id')
                ->constrained('subscription_plan_features', 'subscription_plan_feature_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->integer('consumption_value')->default(0);
            $table->timestamp('last_consumption_at')->nullable()->index('idx_subscription_plan_feature_consumptions_last_consumption_at');

            $table->timestamps();

            $table->index('subscription_id', 'idx_subscription_plan_feature_consumptions_subscription_id');
            $table->index('feature_id', 'idx_subscription_plan_feature_consumptions_feature_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_feature_consumptions');
    }
};
