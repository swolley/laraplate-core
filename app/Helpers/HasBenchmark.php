<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

trait HasBenchmark
{
    protected $benchmarkStartTime;

    protected $benchmarkStartMemory;

    protected $startQueries;

    protected $startRowCount;

    protected ?string $benchmarkTable = null;

    /**
     * Start the benchmark.
     */
    protected function startBenchmark(?string $table = null): void
    {
        $this->bootTime = LARAVEL_START ? microtime(true) - LARAVEL_START : 0;
        $this->benchmarkStartTime = microtime(true);
        $this->benchmarkTable = $table;
        $this->benchmarkStartMemory = memory_get_usage();

        if ($table !== null && $table !== '' && $table !== '0') {
            $this->startRowCount = DB::table($table)->count();
        }
        DB::enableQueryLog();

        $this->startQueries = match (DB::connection()->getDriverName()) {
            'mysql' => (int) DB::select("SHOW SESSION STATUS LIKE 'Questions'")[0]->Value,
            'pgsql' => (int) DB::select('SELECT pg_stat_get_db_xact_commit(pg_backend_pid()) + pg_stat_get_db_xact_rollback(pg_backend_pid()) as count')[0]->count,
            'sqlite' => count(DB::getQueryLog()),  // Richiede DB::enableQueryLog()
            default => 0,
        };
    }

    /**
     * End the benchmark.
     */
    protected function endBenchmark(): void
    {
        if (! $this->benchmarkStartTime) {
            return;
        }

        $executionTime = microtime(true) - $this->benchmarkStartTime;
        $usage = memory_get_usage() - $this->benchmarkStartMemory;

        try {
            if (isset($this->startQueries)) {
                // Get row count after we've stopped tracking queries
                $queriesCount = match (DB::connection()->getDriverName()) {
                    'mysql' => (int) DB::select("SHOW SESSION STATUS LIKE 'Questions'")[0]->Value,
                    'pgsql' => (int) DB::select('SELECT pg_stat_get_db_xact_commit(pg_backend_pid()) + pg_stat_get_db_xact_rollback(pg_backend_pid()) as count')[0]->count,
                    'sqlite' => count(DB::getQueryLog()),  // Richiede DB::enableQueryLog()
                    default => 0,
                };
                $queriesCount = $queriesCount - $this->startQueries + ($this->startQueries > 0 ? -1 : 0); // Subtract the Questions query itself
            } else {
                $queriesCount = 0;
            }

            $rowDiff = $this->benchmarkTable && isset($this->startRowCount) ? DB::table($this->benchmarkTable)->count() - $this->startRowCount : 0;
        } catch (Throwable) {
            $queriesCount = 0;
            $rowDiff = 0;
        }

        DB::disableQueryLog();

        $this->composeOutput($executionTime, $usage, $queriesCount, $rowDiff, $this->bootTime);
    }

    private static function formatTime(float $time): string
    {
        return match (true) {
            $time >= 60 => sprintf('%dm %ds', (int) ($time / 60), (int) ($time - ((int) ($time / 60) * 60))),
            $time >= 1 => round($time, 2) . 's',
            default => round($time * 1000) . 'ms',
        };
    }

    /**
     * Compose the output for the benchmark.
     *
     * @throws BindingResolutionException
     * @throws InvalidArgumentException
     */
    private function composeOutput(float $executionTime, int $memoryUsage, int $queriesCount, int $rowDiff, float $bootTime): void
    {
        // Convert memory usage to a more readable format
        $unit = ['b', 'K', 'M', 'G', 'T', 'P'];
        $usage = round($memoryUsage / 1024 ** $i = floor(log($memoryUsage, 1024)), 2) . $unit[$i];

        // Format boot time
        $formattedBootTime = self::formatTime($bootTime);

        // Format execution time
        $formattedTime = self::formatTime($executionTime);

        // create badges
        $is_in_console = app()->runningInConsole();
        $output_values = [];
        $output = '⚡';
        $this->addBlockToOutput($output, $output_values, 'BOOT', $formattedBootTime, 'bright-blue', 'black', $is_in_console);
        $this->addBlockToOutput($output, $output_values, 'TIME', $formattedTime, 'bright-blue', 'black', $is_in_console);
        $this->addBlockToOutput($output, $output_values, 'MEM', $usage, 'bright-green', 'black', $is_in_console);

        if ($queriesCount !== 0) {
            $this->addBlockToOutput($output, $output_values, 'SQL', number_format($queriesCount), 'bright-magenta', 'black', $is_in_console);
        }

        if ($rowDiff !== 0) {
            $this->addBlockToOutput($output, $output_values, 'ROWS', number_format($rowDiff), 'bright-magenta', 'black', $is_in_console);
        }
        $this->addBlockToOutput($output, $output_values, '', static::class, 'gray', 'black', $is_in_console);
        $output = sprintf($output, ...$output_values);

        $this->displayOutput($output);
    }

    private function displayOutput(string $output): void
    {
        if (app()->runningInConsole()) {
            if ($this instanceof Seeder) {
                $command = $this->command;
            } elseif ($this instanceof \Illuminate\Console\Command && $this->output !== null) {
                $command = $this;
            } else {
                return;
            }

            $command->newLine();
            $command->line($output);
            $command->newLine();
        }

        Log::debug(preg_replace("/\<bg=[\w-]+;fg=[\w-]+\>|\<\/\>/", '', $output));
    }

    /**
     * Add a formatted block to the output.
     */
    private function addBlockToOutput(string &$output, array &$outputValues, string $blockName, mixed $blockValue, string $blockBgColor, string $blockFgColor, bool $isInConsole): void
    {
        if ($blockName !== '' && $blockName !== '0') {
            $blockName .= ' ';
        }
        $output .= $isInConsole ? " <bg={$blockBgColor};fg={$blockFgColor}> {$blockName}%s </>" : " {$blockName}%s";
        $outputValues[] = $blockValue;
    }
}
