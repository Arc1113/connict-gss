<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE ceremonies DROP CONSTRAINT IF EXISTS ceremonies_presentation_mode_check');
        DB::statement(
            "ALTER TABLE ceremonies ADD CONSTRAINT ceremonies_presentation_mode_check CHECK (presentation_mode IN ('automatic', 'fully_automatic', 'controlled'))",
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ceremonies DROP CONSTRAINT IF EXISTS ceremonies_presentation_mode_check');
        DB::statement(
            "ALTER TABLE ceremonies ADD CONSTRAINT ceremonies_presentation_mode_check CHECK (presentation_mode IN ('automatic', 'controlled'))",
        );
    }
};
