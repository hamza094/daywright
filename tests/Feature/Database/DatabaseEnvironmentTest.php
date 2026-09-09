<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

final class DatabaseEnvironmentTest extends TestCase
{
    public function test_ci_uses_the_expected_database_and_enforces_constraints(): void
    {
        $expectedDriver = getenv('CI_DB_CONNECTION');

        if ($expectedDriver === false) {
            $this->markTestSkipped('Database environment checks run in CI.');
        }

        $connection = DB::connection();

        $this->assertSame($expectedDriver, $connection->getDriverName());
        $this->assertSame($expectedDriver, $connection->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME));

        if ($expectedDriver === 'sqlite') {
            $this->assertSame(':memory:', $connection->getDatabaseName());
            $this->assertSame(1, (int) $connection->scalar('PRAGMA foreign_keys'));

            return;
        }

        $this->assertSame('daywright_test', $connection->getDatabaseName());
        $this->assertStringStartsWith(getenv('CI_MYSQL_VERSION').'.', (string) $connection->scalar('SELECT VERSION()'));
        $this->assertSame(1, (int) $connection->scalar('SELECT @@SESSION.foreign_key_checks'));
        $this->assertStringContainsString('STRICT_TRANS_TABLES', (string) $connection->scalar('SELECT @@SESSION.sql_mode'));
    }
}
