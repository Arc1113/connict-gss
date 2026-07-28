<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ceremony_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('current_queue_item_id')->nullable();
            $table->string('display_mode', 24)->default('idle');
            $table->string('audio_status', 24)->default('stopped');
            $table->boolean('is_muted')->default(false);
            $table->unsignedBigInteger('version')->default(0);
            $table->timestampsTz();

            $table->foreign(
                ['ceremony_id', 'current_queue_item_id'],
                'stage_states_ceremony_queue_item_foreign',
            )
                ->references(['ceremony_id', 'id'])
                ->on('stage_queue_items')
                ->restrictOnDelete();
        });

        DB::statement(
            "ALTER TABLE stage_states ADD CONSTRAINT stage_states_display_mode_check
             CHECK (display_mode IN ('idle', 'graduate'))",
        );
        DB::statement(
            "ALTER TABLE stage_states ADD CONSTRAINT stage_states_audio_status_check
             CHECK (audio_status IN ('stopped', 'playing', 'paused', 'failed'))",
        );
        DB::statement(
            'ALTER TABLE stage_states ADD CONSTRAINT stage_states_version_check
             CHECK (version >= 0)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_states');
    }
};
