<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('audio_readiness_status', 24)
                ->default('missing')
                ->after('audio_path');
            $table->timestampTz('audio_uploaded_at')->nullable()->after('audio_readiness_status');
            $table->timestampTz('audio_approved_at')->nullable()->after('audio_uploaded_at');
            $table->foreignId('audio_approved_by')
                ->nullable()
                ->after('audio_approved_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('audio_review_notes')->nullable()->after('audio_approved_by');
            $table->unsignedInteger('audio_duration_seconds')->nullable()->after('audio_review_notes');
            $table->string('audio_mime_type', 128)->nullable()->after('audio_duration_seconds');
            $table->unsignedBigInteger('audio_file_size')->nullable()->after('audio_mime_type');

            $table->index(
                ['ceremony_id', 'audio_readiness_status'],
                'students_ceremony_audio_status_index',
            );
        });

        DB::statement(
            "ALTER TABLE students ADD CONSTRAINT students_audio_readiness_status_check
             CHECK (audio_readiness_status IN ('missing', 'uploaded', 'needs_review', 'approved', 'rejected'))",
        );
        DB::statement(
            "UPDATE students
             SET audio_readiness_status = CASE
                 WHEN audio_path IS NULL THEN 'missing'
                 ELSE 'needs_review'
             END,
             audio_uploaded_at = CASE
                 WHEN audio_path IS NULL THEN NULL
                 ELSE updated_at
             END",
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE students DROP CONSTRAINT IF EXISTS students_audio_readiness_status_check',
        );

        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_ceremony_audio_status_index');
            $table->dropForeign(['audio_approved_by']);
            $table->dropColumn([
                'audio_readiness_status',
                'audio_uploaded_at',
                'audio_approved_at',
                'audio_approved_by',
                'audio_review_notes',
                'audio_duration_seconds',
                'audio_mime_type',
                'audio_file_size',
            ]);
        });
    }
};
