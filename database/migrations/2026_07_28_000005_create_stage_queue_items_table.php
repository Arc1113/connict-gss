<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_queue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ceremony_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id');
            $table->string('status', 24)->default('waiting');
            $table->unsignedInteger('queue_position')->nullable();
            $table->timestampTz('scanned_at')->nullable();
            $table->timestampTz('ready_at')->nullable();
            $table->timestampTz('presented_at')->nullable();
            $table->timestampTz('announced_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->foreignId('scanned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('presented_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampsTz();

            $table->foreign(
                ['ceremony_id', 'student_id'],
                'stage_queue_items_ceremony_student_foreign',
            )
                ->references(['ceremony_id', 'id'])
                ->on('students')
                ->restrictOnDelete();
            $table->unique(
                ['ceremony_id', 'id'],
                'stage_queue_items_ceremony_id_id_unique',
            );
            $table->index(['ceremony_id', 'status', 'queue_position']);
            $table->index(['student_id', 'created_at']);
        });

        DB::statement(
            "ALTER TABLE stage_queue_items ADD CONSTRAINT stage_queue_items_status_check
             CHECK (status IN ('waiting', 'ready', 'presenting', 'announced', 'completed', 'skipped', 'cancelled'))",
        );
        DB::statement(
            'ALTER TABLE stage_queue_items ADD CONSTRAINT stage_queue_items_position_check
             CHECK (queue_position IS NULL OR queue_position > 0)',
        );
        DB::statement(
            "CREATE UNIQUE INDEX stage_queue_items_one_active_student_unique
             ON stage_queue_items (ceremony_id, student_id)
             WHERE status IN ('waiting', 'ready', 'presenting', 'announced')",
        );
        DB::statement(
            "CREATE UNIQUE INDEX stage_queue_items_one_presenting_unique
             ON stage_queue_items (ceremony_id)
             WHERE status = 'presenting'",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_queue_items');
    }
};
