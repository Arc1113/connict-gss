<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ceremony_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->nullable();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->char('raw_token_hash', 64)->nullable();
            $table->string('result', 24);
            $table->string('message', 500)->nullable();
            $table->foreignId('scanned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampTz('scanned_at')->useCurrent();

            $table->index(['ceremony_id', 'scanned_at']);
            $table->index(['student_id', 'scanned_at']);
            $table->index(['result', 'scanned_at']);
            $table->index('raw_token_hash');
        });

        DB::statement(
            'ALTER TABLE scan_logs ADD CONSTRAINT scan_logs_ceremony_student_foreign
             FOREIGN KEY (ceremony_id, student_id)
             REFERENCES students (ceremony_id, id)
             ON DELETE SET NULL (student_id)',
        );
        DB::statement(
            'ALTER TABLE scan_logs ADD CONSTRAINT scan_logs_student_requires_ceremony_check
             CHECK (student_id IS NULL OR ceremony_id IS NOT NULL)',
        );
        DB::statement(
            "ALTER TABLE scan_logs ADD CONSTRAINT scan_logs_result_check
             CHECK (result IN ('accepted', 'invalid', 'duplicate', 'ineligible', 'rejected'))",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_logs');
    }
};
