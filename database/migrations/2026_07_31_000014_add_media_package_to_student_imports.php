<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_imports', function (Blueprint $table): void {
            $table->string('media_package_path')->nullable()->after('source_content');
        });
    }

    public function down(): void
    {
        Schema::table('student_imports', function (Blueprint $table): void {
            $table->dropColumn('media_package_path');
        });
    }
};
