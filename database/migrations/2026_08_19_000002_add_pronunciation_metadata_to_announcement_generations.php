<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcement_generations', function (Blueprint $table): void {
            $table->text('display_text')->nullable()->after('plain_text');
            $table->jsonb('pronunciation_manifest')->nullable()->after('display_text');
        });
        DB::statement(
            'CREATE INDEX announcement_generations_pronunciation_manifest_gin
             ON announcement_generations USING GIN (pronunciation_manifest jsonb_path_ops)',
        );

        DB::table('announcement_generations')->whereNull('display_text')->update([
            'display_text' => DB::raw('plain_text'),
            'pronunciation_manifest' => '[]',
        ]);

        DB::table('announcement_generations')
            ->whereIn('status', ['needs_review', 'approved'])
            ->update(['status' => 'outdated']);
        DB::table('students')
            ->whereIn('audio_source_type', ['azure_tts', 'local_tts'])
            ->whereNotNull('audio_generation_id')
            ->update([
                'audio_readiness_status' => 'needs_review',
                'audio_approved_at' => null,
                'audio_approved_by' => null,
                'audio_review_notes' => 'Generated audio needs review after the pronunciation engine was introduced.',
            ]);
    }

    public function down(): void
    {
        Schema::table('announcement_generations', function (Blueprint $table): void {
            $table->dropColumn(['display_text', 'pronunciation_manifest']);
        });
    }
};
