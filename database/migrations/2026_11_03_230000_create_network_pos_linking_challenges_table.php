<?php

use App\Enums\LinkingChallengeStatus;
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
        Schema::create('network_pos_linking_challenges', function (Blueprint $table) {
            $table->id('challenge_id');
            $table->uuid('challenge_uuid')->unique();
            $table->foreignId('network_id')->constrained('networks', 'network_id')->cascadeOnDelete();
            $table->foreignId('pos_id')->nullable()->constrained('pos', 'pos_id')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('challenge_nonce', 64)->unique();
            $table->text('payload_token');
            $table->string('status', 20)->default(LinkingChallengeStatus::PENDING->value)->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->foreignId('consumed_by_user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->foreignId('consumed_by_pos_id')->nullable()->constrained('pos', 'pos_id')->nullOnDelete();
            $table->timestamps();

            $table->index(['challenge_uuid', 'status'], 'idx_nplc_lookup');
            $table->index('network_id', 'idx_nplc_network');
            $table->index('pos_id', 'idx_nplc_pos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('network_pos_linking_challenges');
    }
};
