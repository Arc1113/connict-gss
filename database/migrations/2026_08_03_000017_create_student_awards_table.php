<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_awards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('award_name');
            $table->string('spoken_text', 500)->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_announcement_enabled')->default(true);
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampsTz();

            $table->index(
                ['student_id', 'display_order', 'id'],
                'student_awards_order_index',
            );
        });

        DB::statement(
            'ALTER TABLE student_awards ADD CONSTRAINT student_awards_name_check
             CHECK (btrim(award_name) <> \'\')',
        );
        DB::statement(
            'ALTER TABLE student_awards ADD CONSTRAINT student_awards_display_order_check
             CHECK (display_order >= 0)',
        );
        DB::statement(
            "ALTER TABLE student_awards ADD CONSTRAINT student_awards_plain_text_check
             CHECK (
                 position('<' in award_name) = 0
                 AND position('>' in award_name) = 0
                 AND (
                     spoken_text IS NULL
                     OR (position('<' in spoken_text) = 0 AND position('>' in spoken_text) = 0)
                 )
             )",
        );
        DB::statement(
            "CREATE UNIQUE INDEX student_awards_exact_content_unique
             ON student_awards (
                 student_id,
                 lower(btrim(award_name)),
                 lower(btrim(COALESCE(spoken_text, '')))
             )",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('student_awards');
    }
};
