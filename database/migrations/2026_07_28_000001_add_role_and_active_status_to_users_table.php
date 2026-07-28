<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->nullable();
            $table->boolean('is_active')->default(true);
        });

        DB::table('users')
            ->whereNull('role')
            ->update(['role' => 'data_encoder']);

        DB::statement('ALTER TABLE users ALTER COLUMN role SET NOT NULL');
        DB::statement(
            "ALTER TABLE users ADD CONSTRAINT users_role_check
             CHECK (role IN ('administrator', 'data_encoder', 'scanner_operator', 'stage_operator', 'technical_operator'))",
        );

        Schema::table('users', function (Blueprint $table) {
            $table->index(['role', 'is_active']);
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'is_active']);
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
