<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_report_presets', function (Blueprint $table): void {
            $table->id('report_preset_id');
            $table->uuid('preset_uuid')->unique();
            $table->foreignId('user_id')->constrained('users', 'user_id')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('network_id')->constrained('networks', 'network_id')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('pos_id')->nullable()->constrained('pos', 'pos_id')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('scope_key', 40);
            $table->string('report_type', 48);
            $table->string('name', 120);
            $table->unsignedInteger('report_schema_version');
            $table->json('criteria');
            $table->json('presentation');
            $table->timestamps();

            $table->unique(
                ['user_id', 'network_id', 'scope_key', 'report_type', 'name'],
                'operational_report_presets_scope_name_unique'
            );
            $table->index(['network_id', 'report_type'], 'operational_report_presets_network_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_report_presets');
    }
};
