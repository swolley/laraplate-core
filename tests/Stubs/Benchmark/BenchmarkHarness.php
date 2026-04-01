<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Benchmark;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Modules\Core\Helpers\HasBenchmark;
use ReflectionMethod;
use ReflectionProperty;

final class BenchmarkHarness extends Command
{
    use HasBenchmark;

    protected $signature = 'bench';

    protected $description = 'test';

    /**
     * Minimal DatabaseManager stand-in so DB::connection() and DB::__call hit the same mocked Connection.
     */
    public static function fakeDbManager(Connection $connection): object
    {
        return new class($connection)
        {
            public function __construct(private Connection $db_connection) {}

            /**
             * @param  array<int, mixed>  $parameters
             */
            public function __call(string $method, array $parameters): mixed
            {
                return $this->db_connection->{$method}(...$parameters);
            }

            public function connection($name = null): Connection
            {
                return $this->db_connection;
            }
        };
    }

    public function testCancelBenchmark(): void
    {
        $this->benchmarkStartTime = microtime(true);
        $this->cancelBenchmark();
        expect($this->benchmarkStartTime)->toBeNull();
    }

    public function testStartEndWithoutTable(): void
    {
        $this->startBenchmark();
        $this->endBenchmark();
    }

    public function testStartEndWithTable(): void
    {
        Schema::create('bench_rows', function (Blueprint $table): void {
            $table->id();
        });
        DB::table('bench_rows')->insert(['id' => 1]);

        $this->startBenchmark('bench_rows');
        DB::table('bench_rows')->insert(['id' => 2]);
        $this->endBenchmark();
    }

    public function testStepBenchmarkWithoutStartIsNoOp(): void
    {
        $this->benchmarkStartTime = null;
        $this->stepBenchmark();
        expect($this->benchmarkStartTime)->toBeNull();
    }

    public function testStepBenchmarkAndRestart(): void
    {
        $this->startBenchmark();
        $this->stepBenchmarkAndRestart();
        expect($this->benchmarkStartTime)->not->toBeNull();
    }

    public function testGetQueryCountUsesSqliteBranch(): void
    {
        $method = new ReflectionMethod(HasBenchmark::class, 'getQueryCount');
        $method->setAccessible(true);
        expect($method->invoke(null))->toBeInt();
    }

    public function testFormatTimeBranches(): void
    {
        $method = new ReflectionMethod(HasBenchmark::class, 'formatTime');
        $method->setAccessible(true);
        expect($method->invoke(null, 90.0))->toContain('m')
            ->and($method->invoke(null, 2.5))->toContain('s')
            ->and($method->invoke(null, 0.05))->toContain('ms');
    }

    public function testStepBenchmarkUsesZeroQueriesWhenStartQueriesUnset(): void
    {
        $this->startBenchmark();
        $prop = new ReflectionProperty(self::class, 'startQueries');
        $prop->setAccessible(true);
        $prop->setValue($this, null);
        $this->stepBenchmark();
    }

    public function testStepBenchmarkSurvivesInvalidBenchmarkTable(): void
    {
        $this->startBenchmark();
        $table_prop = new ReflectionProperty(self::class, 'benchmarkTable');
        $table_prop->setAccessible(true);
        $table_prop->setValue($this, 'bench_missing_table_xyz');
        $row_prop = new ReflectionProperty(self::class, 'startRowCount');
        $row_prop->setAccessible(true);
        $row_prop->setValue($this, 0);
        $this->stepBenchmark();
    }

    public function testGetQueryCountReturnsZeroForUnsupportedDriver(): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('unsupported-driver');

        $original = DB::getFacadeRoot();
        DB::swap(self::fakeDbManager($connection));

        try {
            $method = new ReflectionMethod(HasBenchmark::class, 'getQueryCount');
            $method->setAccessible(true);
            expect($method->invoke(null))->toBe(0);
        } finally {
            DB::swap($original);
        }
    }

    public function testGetQueryCountUsesPgsqlBranch(): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('pgsql');
        $connection->shouldReceive('select')->andReturn([(object) ['count' => 11]]);

        $original = DB::getFacadeRoot();
        DB::swap(self::fakeDbManager($connection));

        try {
            $method = new ReflectionMethod(HasBenchmark::class, 'getQueryCount');
            $method->setAccessible(true);
            expect($method->invoke(null))->toBe(11);
        } finally {
            DB::swap($original);
        }
    }

    public function testGetQueryCountUsesOracleBranch(): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('oracle');
        $connection->shouldReceive('select')->andReturn([(object) ['count' => 4]]);

        $original = DB::getFacadeRoot();
        DB::swap(self::fakeDbManager($connection));

        try {
            $method = new ReflectionMethod(HasBenchmark::class, 'getQueryCount');
            $method->setAccessible(true);
            expect($method->invoke(null))->toBe(4);
        } finally {
            DB::swap($original);
        }
    }
}
