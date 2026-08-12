<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $generationIds = DB::table('announcement_generations as generations')
                ->join('announcement_templates as templates', 'templates.id', '=', 'generations.announcement_template_id')
                ->where('templates.introduction_frequency', 'first_per_program')
                ->whereIn('generations.status', ['needs_review', 'approved'])
                ->pluck('generations.id');

            if ($generationIds->isEmpty()) {
                return;
            }

            DB::table('students')
                ->whereIn('audio_generation_id', $generationIds)
                ->update([
                    'audio_readiness_status' => 'needs_review',
                    'audio_approved_at' => null,
                    'audio_approved_by' => null,
                    'audio_review_notes' => 'Regenerate for course-aware introduction cadence.',
                    'updated_at' => now(),
                ]);

            DB::table('announcement_generations')
                ->whereIn('id', $generationIds)
                ->update([
                    'status' => 'outdated',
                    'review_notes' => 'Regenerate: introduction cadence is now course-aware.',
                    'updated_at' => now(),
                ]);
        });
    }

    public function down(): void
    {
        // Review decisions cannot be reconstructed safely after invalidation.
    }
};
