<?php

namespace Tests\Unit;

// IMPORTANT: Import Laravel's base TestCase, not PHPUnit's
use Tests\TestCase; 
use Illuminate\Support\Facades\DB;

class ExampleTest extends TestCase
{
    // This test checks if the database connection is using SQLite in-memory database: [CONFIG DRIVER: sqlite | ACTUAL PDO DRIVER: sqlite]
    // docker compose exec app env DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Unit/ExampleTest.php
    // kubectl exec deployment/app -n laravel-stack -- env DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Unit/ExampleTest.php
    public function test_database_connection(): void
    {
        $driver = config('database.default');
        $pdoDriver = DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);

        fwrite(STDERR, "\n\n[CONFIG DRIVER: {$driver} | ACTUAL PDO DRIVER: {$pdoDriver}]\n\n");

        $this->assertEquals('sqlite', $pdoDriver);
    }
}