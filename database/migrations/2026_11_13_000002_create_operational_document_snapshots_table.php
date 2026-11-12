<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_document_snapshots', function (Blueprint $table): void {
            $table->id('snapshot_id');
            $table->uuid('snapshot_uuid')->unique();
            $table->foreignId('network_id')->constrained('networks', 'network_id')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('pos_id')->nullable()->constrained('pos', 'pos_id')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('document_type', 40);
            $table->unsignedBigInteger('document_id');
            $table->unsignedInteger('document_version');
            $table->string('document_reference', 80);
            $table->string('template_id', 120);
            $table->string('template_version', 40);
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->char('currency_code', 3)->default('YER');
            $table->json('source_snapshot');
            $table->char('snapshot_sha256', 64);
            $table->string('artifact_path')->nullable();
            $table->char('artifact_sha256', 64)->nullable();
            $table->unsignedBigInteger('artifact_size')->nullable();
            $table->timestamp('issued_at');
            $table->foreignId('created_by')->constrained('users', 'user_id')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['document_type', 'document_id', 'document_version'], 'uq_operational_document_version');
            $table->index(['network_id', 'document_type', 'issued_at'], 'idx_operational_document_network');
            $table->index(['pos_id', 'document_type', 'issued_at'], 'idx_operational_document_pos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_document_snapshots');
    }
};
