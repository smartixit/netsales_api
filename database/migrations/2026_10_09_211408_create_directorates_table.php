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
        Schema::create('directorates', function (Blueprint $table) {
            $table->id('directorate_id');

            $table->foreignId('governorate_id')
                ->constrained('governorates', 'governorate_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('directorate_name', 50);

            $table->timestamps();
            $table->softDeletes()->index('idx_directorates_deleted_at');

            $table->unique(['governorate_id', 'directorate_name'], 'uq_directorates_governorate_directorate');
            $table->index('governorate_id', 'idx_directorates_governorate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('directorates');
    }
};
