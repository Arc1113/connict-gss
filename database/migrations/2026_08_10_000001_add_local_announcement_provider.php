<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcement_templates', function (Blueprint $table): void {
            $table->string('provider', 32)->default('azure')->after('closing_text');
        });
        Schema::table('announcement_generation_batches', function (Blueprint $table): void {
            $table->string('provider', 32)->default('azure')->after('announcement_template_id');
        });

        DB::statement('ALTER TABLE announcement_generations DROP CONSTRAINT announcement_generations_provider_check');
        DB::statement("ALTER TABLE announcement_generations ADD CONSTRAINT announcement_generations_provider_check CHECK (provider IN ('azure', 'kokoro'))");
        DB::statement('ALTER TABLE announcement_voice_samples DROP CONSTRAINT announcement_voice_samples_provider_check');
        DB::statement("ALTER TABLE announcement_voice_samples ADD CONSTRAINT announcement_voice_samples_provider_check CHECK (provider IN ('azure', 'kokoro'))");
        DB::statement("ALTER TABLE announcement_templates ADD CONSTRAINT announcement_templates_provider_check CHECK (provider IN ('azure', 'kokoro'))");
        DB::statement("ALTER TABLE announcement_generation_batches ADD CONSTRAINT announcement_batches_provider_check CHECK (provider IN ('azure', 'kokoro'))");
        DB::statement('ALTER TABLE students DROP CONSTRAINT students_audio_source_type_check');
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_audio_source_type_check CHECK (audio_source_type IS NULL OR audio_source_type IN ('manual_upload', 'azure_tts', 'local_tts'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE students SET audio_source_type = NULL, audio_source_hash = NULL, audio_generation_id = NULL WHERE audio_source_type = 'local_tts'");
        DB::statement("DELETE FROM announcement_voice_samples WHERE provider = 'kokoro'");
        DB::statement("DELETE FROM announcement_generations WHERE provider = 'kokoro'");
        DB::statement("DELETE FROM announcement_generation_batches WHERE provider = 'kokoro'");
        DB::statement("DELETE FROM announcement_templates WHERE provider = 'kokoro'");
        DB::statement('ALTER TABLE students DROP CONSTRAINT students_audio_source_type_check');
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_audio_source_type_check CHECK (audio_source_type IS NULL OR audio_source_type IN ('manual_upload', 'azure_tts'))");
        DB::statement('ALTER TABLE announcement_generations DROP CONSTRAINT announcement_generations_provider_check');
        DB::statement("ALTER TABLE announcement_generations ADD CONSTRAINT announcement_generations_provider_check CHECK (provider = 'azure')");
        DB::statement('ALTER TABLE announcement_voice_samples DROP CONSTRAINT announcement_voice_samples_provider_check');
        DB::statement("ALTER TABLE announcement_voice_samples ADD CONSTRAINT announcement_voice_samples_provider_check CHECK (provider = 'azure')");
        DB::statement('ALTER TABLE announcement_templates DROP CONSTRAINT announcement_templates_provider_check');
        DB::statement('ALTER TABLE announcement_generation_batches DROP CONSTRAINT announcement_batches_provider_check');

        Schema::table('announcement_templates', function (Blueprint $table): void {
            $table->dropColumn('provider');
        });
        Schema::table('announcement_generation_batches', function (Blueprint $table): void {
            $table->dropColumn('provider');
        });
    }
};
