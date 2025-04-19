<?php

namespace Modules\Core\Helpers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;

trait HasBenchmark
{
    protected $benchmarkStartTime;
    protected $benchmarkStartMemory;
    protected $startQueries;
    protected $startRowCount;
    protected ?string $benchmarkTable = null;

    /**
     * Start the benchmark
     * @param string|null $table 
     * @return void
     */
    protected function startBenchmark(?string $table = null): void
    {
        $this->benchmarkTable = $table;
        $db = property_exists($this, 'db') && isset($this->db) ? $this->db : app(DatabaseManager::class);
        $this->benchmarkStartTime = microtime(true);
        $this->benchmarkStartMemory = memory_get_usage();
        if ($table) {
            $this->startRowCount = $db->table($table)->count();
        }
        $db->enableQueryLog();

        $this->startQueries = match ($db->connection()->getDriverName()) {
            'mysql' => (int) $db->select("SHOW SESSION STATUS LIKE 'Questions'")[0]->Value,
            'pgsql' => (int) $db->select("SELECT pg_stat_get_db_xact_commit(pg_backend_pid()) + pg_stat_get_db_xact_rollback(pg_backend_pid()) as count")[0]->count,
            'sqlite' => count($db->getQueryLog()),  // Richiede DB::enableQueryLog()
            default => 0
        };
    }

    /**
     * End the benchmark
     * @return void
     */
    protected function endBenchmark(): void
    {
        if (!$this->benchmarkStartTime) {
            return;
        }

        $db = property_exists($this, 'db') && isset($this->db) ? $this->db : app(DatabaseManager::class);
        $executionTime = microtime(true) - $this->benchmarkStartTime;
        $usage = memory_get_usage() - $this->benchmarkStartMemory;
        try {
            if (isset($this->startQueries)) {
                // Get row count after we've stopped tracking queries
                $queriesCount = match ($db->connection()->getDriverName()) {
                    'mysql' => (int) $db->select("SHOW SESSION STATUS LIKE 'Questions'")[0]->Value,
                    'pgsql' => (int) $db->select("SELECT pg_stat_get_db_xact_commit(pg_backend_pid()) + pg_stat_get_db_xact_rollback(pg_backend_pid()) as count")[0]->count,
                    'sqlite' => count($db->getQueryLog()),  // Richiede DB::enableQueryLog()
                    default => 0
                };
                $queriesCount = $queriesCount - $this->startQueries + ($this->startQueries > 0 ? -1 : 0); // Subtract the Questions query itself
            } else {
                $queriesCount = 0;
            }

            $rowDiff = $this->benchmarkTable && isset($this->startRowCount) ? $db->table($this->benchmarkTable)->count() - $this->startRowCount : 0;
        } catch (\Throwable) {
            $queriesCount = 0;
            $rowDiff = 0;
        }

        $db->disableQueryLog();

        $this->composeOutput($executionTime, $usage, $queriesCount, $rowDiff);
    }

    /**
     * Compose the output for the benchmark
     * @param float $executionTime 
     * @param int $memoryUsage 
     * @param int $queriesCount 
     * @param int $rowDiff 
     * @return void 
     * @throws BindingResolutionException 
     * @throws InvalidArgumentException 
     */
    private function composeOutput(float $executionTime, int $memoryUsage, int $queriesCount, int $rowDiff): void
    {
        // Convert memory usage to a more readable format
        $unit = ['b', 'K', 'M', 'G', 'T', 'P'];
        $usage = round($memoryUsage / 1024 ** $i = floor(log($memoryUsage, 1024)), 2) . $unit[$i];

        // Format execution time
        $formattedTime = match (true) {
            $executionTime >= 60 => sprintf(
                '%dm %ds',
                (int)($executionTime / 60),
                (int)($executionTime - ((int)($executionTime / 60) * 60))
            ),
            $executionTime >= 1 => round($executionTime, 2) . 's',
            default => round($executionTime * 1000) . 'ms',
        };

        // create badges
        $is_in_console = app()->runningInConsole();
        $output_values = [];
        $output = '⚡';
        $this->addBlockToOutput($output, $output_values, 'TIME', $formattedTime, 'bright-blue', 'black', $is_in_console);
        $this->addBlockToOutput($output, $output_values, 'MEM', $usage, 'bright-green', 'black', $is_in_console);
        if ($queriesCount !== 0) {
            $this->addBlockToOutput($output, $output_values, 'SQL', number_format($queriesCount), 'bright-yellow', 'black', $is_in_console);
        }
        if ($rowDiff !== 0) {
            $this->addBlockToOutput($output, $output_values, 'ROWS', number_format($rowDiff), 'bright-magenta', 'black', $is_in_console);
        }
        $this->addBlockToOutput($output, $output_values, '', static::class, 'gray', 'black', $is_in_console);
        $output = sprintf($output, ...$output_values);

        // if ($this instanceof Seeder) {
        //     $this->command->newLine();
        //     $this->command->line($output);
        //     $this->command->newLine();
        // } elseif ($this instanceof \Illuminate\Console\Command && $this->output !== null) {
        //     $this->newLine();
        //     $this->line($output);
        //     $this->newLine();
        // }

        // Log::debug($output);
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

        Log::debug($output);
    }

    /**
     * Add a formatted block to the output
     * @param string &$output 
     * @param array &$outputValues 
     * @param string $blockName 
     * @param mixed $blockValue 
     * @param string $blockBgColor 
     * @param string $blockFgColor 
     * @param bool $isInConsole 
     * @return void 
     */
    private function addBlockToOutput(string &$output, array &$outputValues, string $blockName, mixed $blockValue, string $blockBgColor, string $blockFgColor, bool $isInConsole): void
    {
        if ($blockName !== '' && $blockName !== '0') {
            $blockName .= ' ';
        }
        $output .= $isInConsole ? " <bg=$blockBgColor;fg=$blockFgColor> $blockName%s </>" : " $blockName%s";
        $outputValues[] = $blockValue;
    }
}
