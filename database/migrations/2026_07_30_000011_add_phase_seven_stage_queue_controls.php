<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stage_states', function (Blueprint $table) {
            $table->boolean('automatic_paused')
                ->default(false)
                ->after('is_muted');
        });

        DB::statement(
            'ALTER TABLE stage_states DROP CONSTRAINT stage_states_display_mode_check',
        );
        DB::statement(
            "ALTER TABLE stage_states ADD CONSTRAINT stage_states_display_mode_check
             CHECK (display_mode IN ('idle', 'graduate', 'holding'))",
        );
        DB::statement(
            'CREATE UNIQUE INDEX stage_queue_items_unique_position
             ON stage_queue_items (ceremony_id, queue_position)
             WHERE queue_position IS NOT NULL',
        );
    }

    public function down(): void
    {
        DB::statement(
            "UPDATE stage_states
             SET display_mode = CASE
                 WHEN current_queue_item_id IS NULL THEN 'idle'
                 ELSE 'graduate'
             END
             WHERE display_mode = 'holding'",
        );
        DB::statement(
            'DROP INDEX IF EXISTS stage_queue_items_unique_position',
        );
        DB::statement(
            'ALTER TABLE stage_states DROP CONSTRAINT stage_states_display_mode_check',
        );
        DB::statement(
            "ALTER TABLE stage_states ADD CONSTRAINT stage_states_display_mode_check
             CHECK (display_mode IN ('idle', 'graduate'))",
        );

        Schema::table('stage_states', function (Blueprint $table) {
            $table->dropColumn('automatic_paused');
        });
    }
};
