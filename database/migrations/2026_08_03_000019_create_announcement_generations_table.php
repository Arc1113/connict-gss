<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_generations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('ceremony_id');
            $table->unsignedBigInteger('announcement_template_id');
            $table->string('provider', 32)->default('azure');
            $table->string('provider_voice', 128);
            $table->string('locale', 16);
            $table->text('plain_text')->nullable();
            $table->text('ssml')->nullable();
            $table->char('source_hash', 64);
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('character_count')->nullable();
            $table->string('output_format', 128)->nullable();
            $table->string('candidate_audio_path')->nullable();
            $table->string('provider_request_id', 255)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->foreignId('generated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampTz('generated_at')->nullable();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->string('failure_code', 96)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->string('approved_audio_path')->nullable();
            $table->timestampsTz();

            $table->foreign(
                ['ceremony_id', 'student_id'],
                'announcement_generations_ceremony_student_foreign',
            )
                ->references(['ceremony_id', 'id'])
                ->on('students')
                ->restrictOnDelete();
            $table->foreign(
                ['ceremony_id', 'announcement_template_id'],
                'announcement_generations_ceremony_template_foreign',
            )
                ->references(['ceremony_id', 'id'])
                ->on('announcement_templates')
                ->restrictOnDelete();
            $table->index(
                ['student_id', 'status', 'created_at'],
                'announcement_generations_student_status_index',
            );
            $table->index(
                ['ceremony_id', 'status', 'created_at'],
                'announcement_generations_ceremony_status_index',
            );
            $table->unique(
                ['ceremony_id', 'student_id', 'id'],
                'announcement_generations_ceremony_student_id_unique',
            );
            $table->index('source_hash');
        });

        DB::statement(
            "ALTER TABLE announcement_generations ADD CONSTRAINT announcement_generations_provider_check
             CHECK (provider IN ('azure'))",
        );
        DB::statement(
            "ALTER TABLE announcement_generations ADD CONSTRAINT announcement_generations_status_check
             CHECK (status IN (
                 'pending',
                 'generating',
                 'needs_review',
                 'approved',
                 'rejected',
                 'failed',
                 'outdated',
                 'cancelled'
             ))",
        );
        DB::statement(
            "ALTER TABLE announcement_generations ADD CONSTRAINT announcement_generations_source_hash_check
             CHECK (source_hash ~ '^[0-9a-f]{64}$')",
        );
        DB::statement(
            'ALTER TABLE announcement_generations ADD CONSTRAINT announcement_generations_character_count_check
             CHECK (character_count IS NULL OR character_count >= 0)',
        );
        DB::statement(
            'ALTER TABLE announcement_generations ADD CONSTRAINT announcement_generations_attempt_count_check
             CHECK (attempt_count >= 0)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_generations');
    }
};
