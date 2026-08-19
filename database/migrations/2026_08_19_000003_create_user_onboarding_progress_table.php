<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_onboarding_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tour_key', 64);
            $table->unsignedInteger('tour_version');
            $table->string('status', 24)->default('in_progress');
            $table->unsignedInteger('current_step')->default(0);
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('skipped_at')->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'tour_key', 'tour_version']);
        });

        DB::statement(
            "ALTER TABLE user_onboarding_progress ADD CONSTRAINT user_onboarding_progress_status_check
             CHECK (status IN ('in_progress', 'completed', 'skipped'))",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('user_onboarding_progress');
    }
};

