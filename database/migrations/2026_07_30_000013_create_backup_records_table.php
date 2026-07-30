<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_records', function (Blueprint $table) {
            $table->id();
            $table->string('identifier', 96)->unique();
            $table->string('path_label', 255);
            $table->string('status', 24);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->string('verification_status', 24)->nullable();
            $table->string('failure_summary', 500)->nullable();
            $table->timestampsTz();

            $table->index(['status', 'started_at']);
            $table->index(['created_by', 'started_at']);
        });

        DB::statement(
            "ALTER TABLE backup_records ADD CONSTRAINT backup_records_status_check
             CHECK (status IN ('running', 'completed', 'failed'))",
        );
        DB::statement(
            "ALTER TABLE backup_records ADD CONSTRAINT backup_records_verification_status_check
             CHECK (verification_status IS NULL OR verification_status IN ('pending', 'passed', 'failed'))",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_records');
    }
};
