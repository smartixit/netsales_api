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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id('plan_id');
            $table->string('plan_uuid', 50)->unique('uq_subscription_plans_plan_uuid');
            $table->string('plan_name', 50);
            $table->decimal('plan_price', 15, 2)->default(0.00);
            $table->decimal('plan_discount', 5, 2)->default(0.00);
            $table->integer('plan_validity')->default(30);
            $table->text('plan_desc')->nullable();
            $table->boolean('status')->default(true)->index('idx_subscription_plans_status');

            $table->timestamps();
            $table->softDeletes()->index('idx_subscription_plans_deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
