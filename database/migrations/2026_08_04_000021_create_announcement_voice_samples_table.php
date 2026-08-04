<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_voice_samples', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ceremony_id')->constrained()->restrictOnDelete();
            $table->foreignId('announcement_template_id');
            $table->string('provider', 32)->default('azure');
            $table->string('provider_voice', 128);
            $table->string('locale', 16);
            $table->unsignedInteger('character_count');
            $table->char('source_hash', 64);
            $table->string('candidate_audio_path', 1024);
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->foreign(
                ['ceremony_id', 'announcement_template_id'],
                'announcement_voice_samples_template_ceremony_fk',
            )->references(['ceremony_id', 'id'])
                ->on('announcement_templates')
                ->restrictOnDelete();
            $table->index(['ceremony_id', 'expires_at']);
            $table->index(['generated_by', 'created_at']);
        });

        DB::statement(
            "ALTER TABLE announcement_voice_samples ADD CONSTRAINT announcement_voice_samples_provider_check
             CHECK (provider = 'azure')",
        );
        DB::statement(
            "ALTER TABLE announcement_voice_samples ADD CONSTRAINT announcement_voice_samples_source_hash_check
             CHECK (source_hash ~ '^[0-9a-f]{64}$')",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_voice_samples');
    }
};
