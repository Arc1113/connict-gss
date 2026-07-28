<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('device_type', 24);
            $table->char('key_hash', 64)->nullable()->unique();
            $table->string('ip_address', 45)->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->string('status', 24)->default('inactive');
            $table->timestampsTz();

            $table->index(['device_type', 'status']);
        });

        DB::statement(
            "ALTER TABLE devices ADD CONSTRAINT devices_type_check
             CHECK (device_type IN ('server', 'scanner', 'stage', 'control'))",
        );
        DB::statement(
            "ALTER TABLE devices ADD CONSTRAINT devices_status_check
             CHECK (status IN ('inactive', 'active', 'disabled'))",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
