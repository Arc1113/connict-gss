<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_design_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description', 500)->nullable();
            $table->jsonb('theme');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        $now = now();

        DB::table('stage_design_templates')->insert([
            [
                'name' => 'Institutional green',
                'description' => 'A formal centered design with the school palette and optional graduate portrait.',
                'theme' => json_encode([
                    'preset' => 'institutional',
                    'layout' => 'centered',
                    'background_color' => '#082f2b',
                    'surface_color' => '#f6f1e7',
                    'text_color' => '#ffffff',
                    'accent_color' => '#d6b474',
                    'show_photo' => true,
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Modern minimal',
                'description' => 'A clean portrait split with restrained contrast for contemporary ceremonies.',
                'theme' => json_encode([
                    'preset' => 'modern',
                    'layout' => 'split',
                    'background_color' => '#102b2a',
                    'surface_color' => '#e7eee9',
                    'text_color' => '#ffffff',
                    'accent_color' => '#9dbfae',
                    'show_photo' => true,
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Classic ceremony',
                'description' => 'A traditional lower-banner treatment suited to formal commencement programs.',
                'theme' => json_encode([
                    'preset' => 'classic',
                    'layout' => 'banner',
                    'background_color' => '#231d18',
                    'surface_color' => '#f1e7d2',
                    'text_color' => '#fffaf0',
                    'accent_color' => '#c7a76b',
                    'show_photo' => false,
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_design_templates');
    }
};
