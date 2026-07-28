<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    public function test_the_test_suite_uses_the_postgresql_testing_database(): void
    {
        $connection = DB::connection();
        $database = $connection->selectOne('select current_database() as name');

        $this->assertSame('pgsql', $connection->getDriverName());
        $this->assertSame('graduation_stage_system_testing', $database->name);
    }
}
