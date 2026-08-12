<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $affectedGenerationIds = DB::table('announcement_generations')
                ->where('provider', 'kokoro')
                ->whereIn('status', ['needs_review', 'approved'])
                ->pluck('id');

            if ($affectedGenerationIds->isEmpty()) {
                return;
            }

            DB::table('students')
                ->whereIn('audio_generation_id', $affectedGenerationIds)
                ->update([
                    'audio_readiness_status' => 'needs_review',
                    'audio_approved_at' => null,
                    'audio_approved_by' => null,
                    'audio_review_notes' => 'Offline audio requires regeneration with the professional MC delivery engine.',
                    'updated_at' => now(),
                ]);

            DB::table('announcement_generations')
                ->whereIn('id', $affectedGenerationIds)
                ->update([
                    'status' => 'outdated',
                    'review_notes' => 'Regenerate: local synthesis now uses continuous professional MC delivery.',
                    'updated_at' => now(),
                ]);
        });
    }

    public function down(): void
    {
        // Review decisions cannot be reconstructed safely after invalidation.
    }
};
