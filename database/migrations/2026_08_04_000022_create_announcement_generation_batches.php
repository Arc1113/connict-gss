<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_generation_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ceremony_id')->constrained()->restrictOnDelete();
            $table->foreignId('announcement_template_id');
            $table->string('provider_voice', 128);
            $table->string('locale', 16);
            $table->char('selection_hash', 64);
            $table->string('status', 24)->default('queued');
            $table->unsignedInteger('selected_count');
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('succeeded_count')->default(0);
            $table->unsignedInteger('reused_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('estimated_character_count');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('cancellation_requested_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->string('last_failure_code', 96)->nullable();
            $table->string('last_failure_message', 500)->nullable();
            $table->timestampsTz();

            $table->foreign(
                ['ceremony_id', 'announcement_template_id'],
                'announcement_batches_template_ceremony_fk',
            )->references(['ceremony_id', 'id'])
                ->on('announcement_templates')
                ->restrictOnDelete();
            $table->index(['ceremony_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        DB::statement(
            "ALTER TABLE announcement_generation_batches ADD CONSTRAINT announcement_batches_status_check
             CHECK (status IN ('queued', 'running', 'paused', 'completed', 'cancelled'))",
        );
        DB::statement(
            'ALTER TABLE announcement_generation_batches ADD CONSTRAINT announcement_batches_counts_check
             CHECK (selected_count > 0 AND processed_count <= selected_count
                 AND succeeded_count <= processed_count AND reused_count <= processed_count
                 AND failed_count <= processed_count
                 AND succeeded_count + reused_count + failed_count <= processed_count)',
        );
        DB::statement(
            "CREATE UNIQUE INDEX announcement_batches_active_selection_unique
             ON announcement_generation_batches (ceremony_id, selection_hash)
             WHERE status IN ('queued', 'running', 'paused')",
        );

        Schema::create('announcement_generation_batch_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_generation_batch_id')
                ->constrained('announcement_generation_batches')
                ->cascadeOnDelete();
            $table->foreignId('ceremony_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id');
            $table->unsignedInteger('position');
            $table->string('status', 24)->default('queued');
            $table->unsignedInteger('character_count');
            $table->foreignId('announcement_generation_id')
                ->nullable()
                ->constrained('announcement_generations')
                ->nullOnDelete();
            $table->string('failure_code', 96)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();

            $table->foreign(
                ['ceremony_id', 'student_id'],
                'announcement_batch_items_student_ceremony_fk',
            )->references(['ceremony_id', 'id'])
                ->on('students')
                ->restrictOnDelete();
            $table->unique(
                ['announcement_generation_batch_id', 'student_id'],
                'announcement_batch_items_student_unique',
            );
            $table->unique(
                ['announcement_generation_batch_id', 'position'],
                'announcement_batch_items_position_unique',
            );
            $table->index(['announcement_generation_batch_id', 'status', 'position']);
        });

        DB::statement(
            "ALTER TABLE announcement_generation_batch_items ADD CONSTRAINT announcement_batch_items_status_check
             CHECK (status IN ('queued', 'processing', 'succeeded', 'reused', 'failed', 'cancelled'))",
        );
        DB::statement(
            'ALTER TABLE announcement_generation_batch_items ADD CONSTRAINT announcement_batch_items_position_check
             CHECK (position > 0)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_generation_batch_items');
        Schema::dropIfExists('announcement_generation_batches');
    }
};
