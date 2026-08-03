<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ceremony_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('introduction_text', 500)->default('We proudly present');
            $table->boolean('include_program')->default(true);
            $table->boolean('include_major')->default(true);
            $table->boolean('include_honors')->default(true);
            $table->boolean('include_awards')->default(true);
            $table->boolean('include_additional_text')->default(false);
            $table->string('awards_lead_in', 255)->nullable();
            $table->string('closing_text', 500)->nullable();
            $table->string('default_voice', 128)->default('en-PH-JamesNeural');
            $table->string('default_locale', 16)->default('en-PH');
            $table->smallInteger('speaking_rate_percent')->default(-5);
            $table->unsignedSmallInteger('pause_after_name_ms')->default(250);
            $table->unsignedSmallInteger('pause_between_sections_ms')->default(160);
            $table->unsignedSmallInteger('recommended_character_limit')->default(450);
            $table->unsignedSmallInteger('maximum_character_limit')->default(700);
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampsTz();

            $table->unique(
                ['ceremony_id', 'version'],
                'announcement_templates_ceremony_version_unique',
            );
            $table->unique(
                ['ceremony_id', 'id'],
                'announcement_templates_ceremony_id_id_unique',
            );
            $table->index(
                ['ceremony_id', 'created_at'],
                'announcement_templates_ceremony_created_index',
            );
        });

        DB::statement(
            'ALTER TABLE announcement_templates ADD CONSTRAINT announcement_templates_version_check
             CHECK (version > 0)',
        );
        DB::statement(
            'ALTER TABLE announcement_templates ADD CONSTRAINT announcement_templates_rate_check
             CHECK (speaking_rate_percent BETWEEN -50 AND 100)',
        );
        DB::statement(
            'ALTER TABLE announcement_templates ADD CONSTRAINT announcement_templates_pause_check
             CHECK (pause_after_name_ms <= 5000 AND pause_between_sections_ms <= 5000)',
        );
        DB::statement(
            'ALTER TABLE announcement_templates ADD CONSTRAINT announcement_templates_character_limits_check
             CHECK (
                 recommended_character_limit > 0
                 AND maximum_character_limit >= recommended_character_limit
             )',
        );
        DB::statement(
            "ALTER TABLE announcement_templates ADD CONSTRAINT announcement_templates_plain_text_check
             CHECK (
                 position('<' in introduction_text) = 0
                 AND position('>' in introduction_text) = 0
                 AND (
                     awards_lead_in IS NULL
                     OR (position('<' in awards_lead_in) = 0 AND position('>' in awards_lead_in) = 0)
                 )
                 AND (
                     closing_text IS NULL
                     OR (position('<' in closing_text) = 0 AND position('>' in closing_text) = 0)
                 )
             )",
        );
        DB::statement(
            'CREATE UNIQUE INDEX announcement_templates_one_active_per_ceremony_unique
             ON announcement_templates (ceremony_id)
             WHERE is_active',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_templates');
    }
};
