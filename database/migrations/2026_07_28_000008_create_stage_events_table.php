<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ceremony_id')->constrained()->restrictOnDelete();
            $table->foreignId('queue_item_id')->nullable();
            $table->string('event_type', 64);
            $table->jsonb('payload')->nullable();
            $table->foreignId('performed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign(
                ['ceremony_id', 'queue_item_id'],
                'stage_events_ceremony_queue_item_foreign',
            )
                ->references(['ceremony_id', 'id'])
                ->on('stage_queue_items')
                ->restrictOnDelete();
            $table->index(['ceremony_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_events');
    }
};
