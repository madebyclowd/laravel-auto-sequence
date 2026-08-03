<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Sequencing;

use Illuminate\Database\Connection;
use MadeByClowd\AutoSequence\SequenceManager;
use MadeByClowd\AutoSequence\Tests\TestCase;
use Mockery;
use ReflectionMethod;

/**
 * setDatabaseLockTimeout() applies a driver-specific SQL statement to bound
 * how long a transaction waits for a sequence row lock. Each branch needs a
 * real MySQL/Postgres/SQL Server connection to exercise naturally (the test
 * suite only runs against SQLite), so the driver dispatch and its silent
 * error-swallowing are verified directly against a mocked Connection instead.
 */
class DatabaseLockTimeoutTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function invoke(Connection $connection, int $timeoutSeconds): void
    {
        $manager = new SequenceManager;
        $method = new ReflectionMethod($manager, 'setDatabaseLockTimeout');
        $method->setAccessible(true);
        $method->invoke($manager, $connection, $timeoutSeconds);
    }

    /** @test */
    public function test_it_sets_mysql_innodb_lock_wait_timeout()
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('mysql');
        $connection->shouldReceive('statement')
            ->once()
            ->with('SET SESSION innodb_lock_wait_timeout = 5');

        $this->invoke($connection, 5);

        $this->assertTrue(true);
    }

    /** @test */
    public function test_it_sets_postgres_lock_timeout_in_milliseconds()
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('pgsql');
        $connection->shouldReceive('statement')
            ->once()
            ->with('SET LOCAL lock_timeout = 5000');

        $this->invoke($connection, 5);

        $this->assertTrue(true);
    }

    /** @test */
    public function test_it_sets_sql_server_lock_timeout_in_milliseconds()
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('sqlsrv');
        $connection->shouldReceive('statement')
            ->once()
            ->with('SET LOCK_TIMEOUT 5000');

        $this->invoke($connection, 5);

        $this->assertTrue(true);
    }

    /** @test */
    public function test_it_sets_sqlite_busy_timeout_in_milliseconds()
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('sqlite');
        $connection->shouldReceive('statement')
            ->once()
            ->with('PRAGMA busy_timeout = 5000');

        $this->invoke($connection, 5);

        $this->assertTrue(true);
    }

    /** @test */
    public function test_it_does_nothing_for_an_unrecognized_driver()
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('oracle');
        $connection->shouldNotReceive('statement');

        $this->invoke($connection, 5);

        $this->assertTrue(true);
    }

    /** @test */
    public function test_it_silently_swallows_errors_when_the_statement_fails()
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('mysql');
        $connection->shouldReceive('statement')
            ->once()
            ->andThrow(new \RuntimeException('access denied for this user'));

        // Should not propagate — a user lacking permission to set this
        // session variable must not break sequence generation.
        $this->invoke($connection, 5);

        $this->assertTrue(true);
    }
}
