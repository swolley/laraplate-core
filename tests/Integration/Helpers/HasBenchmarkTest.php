<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\HasBenchmark;
use Modules\Core\Tests\Stubs\Benchmark\BenchmarkHarness;
use Modules\Core\Tests\Stubs\Benchmark\BenchmarkSeederHarness;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;


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

it('reports the exact sql query count in the benchmark output', function (): void {
    Log::spy();

    $cmd = new BenchmarkHarness;
    $cmd->setLaravel($this->app);
    prepare_benchmark_command($cmd);

    $cmd->testQueryCountAccuracy(3);

    Log::shouldHaveReceived('debug')
        ->once()
        ->withArgs(fn (string $message): bool => str_contains($message, 'SQL 3'));
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

it('does not throw when a seeder has no command instance', function (): void {
    $seeder = new class extends Seeder
    {
        use HasBenchmark;

        public function emitBenchmarkOutput(string $output): void
        {
            $method = new ReflectionMethod($this, 'displayOutput');
            $method->setAccessible(true);
            $method->invoke($this, $output);
        }
    };

    expect(fn () => $seeder->emitBenchmarkOutput('bench output'))->not->toThrow(Error::class);
});
