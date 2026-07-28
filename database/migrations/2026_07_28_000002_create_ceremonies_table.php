<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ceremonies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('academic_year', 32)->nullable();
            $table->string('venue')->nullable();
            $table->date('event_date')->nullable();
            $table->string('status', 24)->default('draft');
            $table->string('presentation_mode', 24)->default('controlled');
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'event_date']);
        });

        DB::statement(
            "ALTER TABLE ceremonies ADD CONSTRAINT ceremonies_status_check
             CHECK (status IN ('draft', 'prepared', 'active', 'paused', 'completed', 'archived'))",
        );
        DB::statement(
            "ALTER TABLE ceremonies ADD CONSTRAINT ceremonies_presentation_mode_check
             CHECK (presentation_mode IN ('automatic', 'controlled'))",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ceremonies');
    }
};
