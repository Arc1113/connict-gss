<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->string('tts_spoken_name')->nullable()->after('pronunciation_guide');
            $table->string('announcement_additional_text', 700)->nullable()->after('honors');
            $table->string('audio_source_type', 24)->nullable()->after('audio_path');
            $table->char('audio_source_hash', 64)->nullable()->after('audio_source_type');

            $table->index(
                ['ceremony_id', 'audio_source_type'],
                'students_ceremony_audio_source_index',
            );
        });

        DB::statement(
            "ALTER TABLE students ADD CONSTRAINT students_audio_source_type_check
             CHECK (audio_source_type IS NULL OR audio_source_type IN ('manual_upload', 'azure_tts'))",
        );
        DB::statement(
            "ALTER TABLE students ADD CONSTRAINT students_audio_source_hash_check
             CHECK (audio_source_hash IS NULL OR audio_source_hash ~ '^[0-9a-f]{64}$')",
        );
        DB::statement(
            "ALTER TABLE students ADD CONSTRAINT students_tts_spoken_name_plain_text_check
             CHECK (
                 tts_spoken_name IS NULL
                 OR (position('<' in tts_spoken_name) = 0 AND position('>' in tts_spoken_name) = 0)
             )",
        );
        DB::statement(
            "ALTER TABLE students ADD CONSTRAINT students_announcement_additional_text_plain_text_check
             CHECK (
                 announcement_additional_text IS NULL
                 OR (
                     position('<' in announcement_additional_text) = 0
                     AND position('>' in announcement_additional_text) = 0
                 )
             )",
        );
        DB::statement(
            "UPDATE students
             SET audio_source_type = 'manual_upload'
             WHERE audio_path IS NOT NULL",
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE students DROP CONSTRAINT IF EXISTS students_announcement_additional_text_plain_text_check',
        );
        DB::statement(
            'ALTER TABLE students DROP CONSTRAINT IF EXISTS students_tts_spoken_name_plain_text_check',
        );
        DB::statement(
            'ALTER TABLE students DROP CONSTRAINT IF EXISTS students_audio_source_hash_check',
        );
        DB::statement(
            'ALTER TABLE students DROP CONSTRAINT IF EXISTS students_audio_source_type_check',
        );

        Schema::table('students', function (Blueprint $table): void {
            $table->dropIndex('students_ceremony_audio_source_index');
            $table->dropColumn([
                'tts_spoken_name',
                'announcement_additional_text',
                'audio_source_type',
                'audio_source_hash',
            ]);
        });
    }
};
