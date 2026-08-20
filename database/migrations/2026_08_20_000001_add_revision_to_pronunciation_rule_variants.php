<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pronunciation_rule_variants', function (Blueprint $table): void {
            $table->unsignedInteger('revision')->default(1);
        });

        DB::statement(
            'ALTER TABLE pronunciation_rule_variants ADD CONSTRAINT pronunciation_rule_variants_revision_check CHECK (revision > 0)',
        );
    }

    public function down(): void
    {
        Schema::table('pronunciation_rule_variants', function (Blueprint $table): void {
            $table->dropColumn('revision');
        });
    }
};
