<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pronunciation_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ceremony_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('section', 32)->default('any');
            $table->string('match_text', 255);
            $table->string('normalized_match', 255);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_builtin')->default(false);
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['ceremony_id', 'is_enabled']);
        });

        DB::statement(
            "ALTER TABLE pronunciation_rules ADD CONSTRAINT pronunciation_rules_section_check
             CHECK (section IN ('any', 'name', 'program', 'major', 'honors', 'award', 'template', 'additional'))",
        );
        DB::statement(
            'ALTER TABLE pronunciation_rules ADD CONSTRAINT pronunciation_rules_revision_check CHECK (revision > 0)',
        );
        DB::statement(
            'CREATE UNIQUE INDEX pronunciation_rules_common_unique
             ON pronunciation_rules (section, normalized_match)
             WHERE ceremony_id IS NULL',
        );
        DB::statement(
            'CREATE UNIQUE INDEX pronunciation_rules_ceremony_unique
             ON pronunciation_rules (ceremony_id, section, normalized_match)
             WHERE ceremony_id IS NOT NULL',
        );

        Schema::create('pronunciation_rule_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pronunciation_rule_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('locale', 16)->default('');
            $table->string('voice', 128)->default('');
            $table->string('replacement_text', 255);
            $table->boolean('is_enabled')->default(true);
            $table->timestampsTz();

            $table->unique(
                ['pronunciation_rule_id', 'provider', 'locale', 'voice'],
                'pronunciation_rule_variants_context_unique',
            );
        });

        DB::statement(
            "ALTER TABLE pronunciation_rule_variants ADD CONSTRAINT pronunciation_rule_variants_provider_check
             CHECK (provider IN ('azure', 'kokoro'))",
        );

        $now = now();
        $builtIns = [
            ['section' => 'any', 'match' => 'Ph.D.', 'replacement' => 'Doctor of Philosophy'],
            ['section' => 'any', 'match' => 'GPA', 'replacement' => 'G P A'],
            ['section' => 'name', 'match' => 'Dr.', 'replacement' => 'Doctor'],
        ];

        foreach ($builtIns as $builtIn) {
            $ruleId = DB::table('pronunciation_rules')->insertGetId([
                'ceremony_id' => null,
                'section' => $builtIn['section'],
                'match_text' => $builtIn['match'],
                'normalized_match' => mb_strtolower($builtIn['match'], 'UTF-8'),
                'is_enabled' => true,
                'is_builtin' => true,
                'revision' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (['azure', 'kokoro'] as $provider) {
                DB::table('pronunciation_rule_variants')->insert([
                    'pronunciation_rule_id' => $ruleId,
                    'provider' => $provider,
                    'locale' => '',
                    'voice' => '',
                    'replacement_text' => $builtIn['replacement'],
                    'is_enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pronunciation_rule_variants');
        Schema::dropIfExists('pronunciation_rules');
    }
};
