<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ceremony_id')->constrained()->cascadeOnDelete();
            $table->string('student_number', 64);
            $table->string('qr_token', 128)->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix', 32)->nullable();
            $table->string('display_name');
            $table->string('announcement_name');
            $table->string('pronunciation_guide')->nullable();
            $table->string('college')->nullable();
            $table->string('program')->nullable();
            $table->string('major')->nullable();
            $table->text('honors')->nullable();
            $table->unsignedInteger('sequence_number');
            $table->string('photo_path')->nullable();
            $table->string('audio_path')->nullable();
            $table->string('eligibility_status', 24)->default('pending');
            $table->timestampsTz();

            $table->unique(
                ['ceremony_id', 'student_number'],
                'students_ceremony_student_number_unique',
            );
            $table->unique(
                ['ceremony_id', 'sequence_number'],
                'students_ceremony_sequence_unique',
            );
            $table->unique(
                ['ceremony_id', 'id'],
                'students_ceremony_id_id_unique',
            );
            $table->index(['ceremony_id', 'last_name', 'first_name']);
            $table->index(['ceremony_id', 'eligibility_status']);
        });

        DB::statement(
            "ALTER TABLE students ADD CONSTRAINT students_eligibility_status_check
             CHECK (eligibility_status IN ('pending', 'eligible', 'ineligible'))",
        );
        DB::statement(
            'ALTER TABLE students ADD CONSTRAINT students_sequence_number_check
             CHECK (sequence_number > 0)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
