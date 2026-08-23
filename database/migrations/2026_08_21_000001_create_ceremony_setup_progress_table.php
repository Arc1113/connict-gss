<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ceremony_setup_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ceremony_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('current_step')->nullable();
            $table->json('skipped_steps')->default('[]');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['ceremony_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ceremony_setup_progress');
    }
};
