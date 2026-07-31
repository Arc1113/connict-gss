<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ceremonies', function (Blueprint $table): void {
            $table->jsonb('stage_theme')->nullable()->after('presentation_mode');
            $table->string('stage_logo_path')->nullable()->after('stage_theme');
            $table->string('stage_background_path')->nullable()->after('stage_logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('ceremonies', function (Blueprint $table): void {
            $table->dropColumn([
                'stage_theme',
                'stage_logo_path',
                'stage_background_path',
            ]);
        });
    }
};
