<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_pos', function (Blueprint $table): void {
            $table->softDeletes();
            $table->index(['network_id', 'deleted_at'], 'idx_network_pos_network_deleted');
            $table->index(['pos_id', 'deleted_at'], 'idx_network_pos_pos_deleted');
        });

        Schema::create('network_pos_agreements', function (Blueprint $table): void {
            $table->id('agreement_id');
            $table->foreignId('network_pos_id')
                ->constrained('network_pos', 'network_pos_id')
                ->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('title', 180);
            $table->string('reference', 100)->nullable();
            $table->longText('terms');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->string('status', 20)->default('DRAFT');
            $table->foreignId('created_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('terminated_at')->nullable();
            $table->timestamps();
            $table->unique(['network_pos_id', 'version'], 'uq_network_pos_agreement_version');
            $table->index(['network_pos_id', 'status', 'ends_at'], 'idx_network_pos_agreement_expiry');
        });

        Schema::create('network_pos_events', function (Blueprint $table): void {
            $table->id('event_id');
            $table->foreignId('network_pos_id')
                ->constrained('network_pos', 'network_pos_id')
                ->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()
                ->constrained('users', 'user_id')->nullOnDelete();
            $table->string('event_type', 60);
            $table->json('payload')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->index(['network_pos_id', 'event_id'], 'idx_network_pos_events_timeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_pos_events');
        Schema::dropIfExists('network_pos_agreements');
        // The agreement table owns its indexes and is dropped above.
        Schema::table('network_pos', function (Blueprint $table): void {
            $table->dropIndex('idx_network_pos_network_deleted');
            $table->dropIndex('idx_network_pos_pos_deleted');
            $table->dropSoftDeletes();
        });
    }
};
