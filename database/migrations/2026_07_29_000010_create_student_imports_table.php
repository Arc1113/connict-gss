<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_imports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ceremony_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('original_filename');
            $table->string('mode', 32)->default('create_only');
            $table->char('delimiter', 1);
            $table->jsonb('headers');
            $table->jsonb('mapping')->nullable();
            $table->text('source_content')->nullable();
            $table->unsignedInteger('row_count');
            $table->jsonb('summary')->nullable();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_imports');
    }
};
