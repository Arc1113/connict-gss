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
            $table->string('introduction_frequency', 32)
                ->default('first_per_program')
                ->after('introduction_text');
        });

        DB::statement(
            "ALTER TABLE announcement_templates ADD CONSTRAINT announcement_templates_introduction_frequency_check
             CHECK (introduction_frequency IN ('first_per_program', 'every_student', 'never'))",
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE announcement_templates DROP CONSTRAINT IF EXISTS announcement_templates_introduction_frequency_check');
        Schema::table('announcement_templates', function (Blueprint $table): void {
            $table->dropColumn('introduction_frequency');
        });
    }
};
