<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\HasBenchmark;
use Modules\Core\Tests\LaravelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

uses(LaravelTestCase::class);

/**
 * Minimal DatabaseManager stand-in so DB::connection() and DB::__call (e.g. select) hit the same mocked Connection.
 */
function benchmark_fake_db_manager(Connection $connection): object
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

final class BenchmarkHarness extends Command
{
    use HasBenchmark;

    protected $signature = 'bench';

    protected $description = 'test';

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
        DB::swap(benchmark_fake_db_manager($connection));

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
        DB::swap(benchmark_fake_db_manager($connection));

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
        DB::swap(benchmark_fake_db_manager($connection));

        try {
            $method = new ReflectionMethod(HasBenchmark::class, 'getQueryCount');
            $method->setAccessible(true);
            expect($method->invoke(null))->toBe(4);
        } finally {
            DB::swap($original);
        }
    }
}

final class BenchmarkSeederHarness extends Seeder
{
    use HasBenchmark;

    public function runBenchmark(Command $command): void
    {
        $this->command = $command;
        $this->startBenchmark();
        $this->endBenchmark();
    }
}

function prepare_benchmark_command(BenchmarkHarness $command): void
{
    $input = new ArrayInput([]);
    $input->bind($command->getDefinition());
    $command->setInput($input);
    $command->setOutput(new OutputStyle($input, new NullOutput));
}

beforeEach(function (): void {
    Schema::dropIfExists('bench_rows');
});

it('cancels benchmark and clears start time', function (): void {
    $cmd = new BenchmarkHarness;
    $cmd->setLaravel($this->app);
    prepare_benchmark_command($cmd);
    $cmd->testCancelBenchmark();
});

it('runs benchmark without table and logs output', function (): void {
    Log::spy();

    $cmd = new BenchmarkHarness;
    $cmd->setLaravel($this->app);
    prepare_benchmark_command($cmd);
    $cmd->testStartEndWithoutTable();

    Log::shouldHaveReceived('debug');
});

it('counts rows when benchmark table is set', function (): void {
    Log::spy();

    $cmd = new BenchmarkHarness;
    $cmd->setLaravel($this->app);
    prepare_benchmark_command($cmd);
    $cmd->testStartEndWithTable();

    Log::shouldHaveReceived('debug');
});

it('stepBenchmark returns early when not started', function (): void {
    $cmd = new BenchmarkHarness;
    $cmd->setLaravel($this->app);
    prepare_benchmark_command($cmd);
    $cmd->testStepBenchmarkWithoutStartIsNoOp();
});

it('stepBenchmarkAndRestart keeps timing running', function (): void {
    Log::spy();

    $cmd = new BenchmarkHarness;
    $cmd->setLaravel($this->app);
    prepare_benchmark_command($cmd);
    $cmd->testStepBenchmarkAndRestart();

    Log::shouldHaveReceived('debug');
});

it('exposes sqlite query count and time formatting', function (): void {
    $cmd = new BenchmarkHarness;
    $cmd->setLaravel($this->app);
    prepare_benchmark_command($cmd);
    $cmd->testGetQueryCountUsesSqliteBranch();
    $cmd->testFormatTimeBranches();
});

it('treats missing startQueries as zero in stepBenchmark', function (): void {
    Log::spy();

    $cmd = new BenchmarkHarness;
    $cmd->setLaravel($this->app);
    prepare_benchmark_command($cmd);
    $cmd->testStepBenchmarkUsesZeroQueriesWhenStartQueriesUnset();

    Log::shouldHaveReceived('debug');
});

it('stepBenchmark tolerates missing benchmark table when counting rows', function (): void {
    Log::spy();

    $cmd = new BenchmarkHarness;
    $cmd->setLaravel($this->app);
    prepare_benchmark_command($cmd);
    $cmd->testStepBenchmarkSurvivesInvalidBenchmarkTable();

    Log::shouldHaveReceived('debug');
});

it('skips writing to console when command output is not set', function (): void {
    $cmd = new BenchmarkHarness;
    $cmd->setLaravel($this->app);
    prepare_benchmark_command($cmd);

    $output_prop = new ReflectionProperty(BenchmarkHarness::class, 'output');
    $output_prop->setAccessible(true);
    $output_prop->setValue($cmd, null);

    $console_prop = new ReflectionProperty(Application::class, 'isRunningInConsole');
    $console_prop->setAccessible(true);
    $console_prop->setValue($this->app, true);

    $display = new ReflectionMethod(BenchmarkHarness::class, 'displayOutput');
    $display->setAccessible(true);
    $display->invoke($cmd, 'bench');

    expect(app()->runningInConsole())->toBeTrue();
});

it('returns zero from getQueryCount for unsupported drivers', function (): void {
    $cmd = new BenchmarkHarness;
    $cmd->setLaravel($this->app);
    prepare_benchmark_command($cmd);
    $cmd->testGetQueryCountReturnsZeroForUnsupportedDriver();
});

it('uses pgsql and oracle branches in getQueryCount', function (): void {
    $cmd = new BenchmarkHarness;
    $cmd->setLaravel($this->app);
    prepare_benchmark_command($cmd);
    $cmd->testGetQueryCountUsesPgsqlBranch();
    $cmd->testGetQueryCountUsesOracleBranch();
});

it('writes benchmark output when used from a seeder with command set', function (): void {
    Log::spy();

    $cmd = new BenchmarkHarness;
    $cmd->setLaravel($this->app);
    prepare_benchmark_command($cmd);

    $seeder = new BenchmarkSeederHarness;
    $seeder->setContainer($this->app);
    $seeder->runBenchmark($cmd);

    Log::shouldHaveReceived('debug');
});
