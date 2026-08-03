<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->unsignedBigInteger('audio_generation_id')
                ->nullable()
                ->after('audio_source_type');

            $table->foreign(
                ['ceremony_id', 'id', 'audio_generation_id'],
                'students_active_audio_generation_foreign',
            )
                ->references(['ceremony_id', 'student_id', 'id'])
                ->on('announcement_generations')
                ->restrictOnDelete();
            $table->index('audio_generation_id');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropForeign('students_active_audio_generation_foreign');
            $table->dropIndex(['audio_generation_id']);
            $table->dropColumn('audio_generation_id');
        });
    }
};
