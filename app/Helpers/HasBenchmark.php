<?php

namespace Modules\Core\Helpers;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\DatabaseManager;

trait HasBenchmark
{
    protected $benchmarkStartTime;
    protected $benchmarkStartMemory;
    protected $startQueries;
    protected $startRowCount;

    protected function startBenchmark(?string $table = null): void
    {
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

    protected function endBenchmark(?string $table = null): void
    {
        $db = property_exists($this, 'db') && isset($this->db) ? $this->db : app(DatabaseManager::class);
        $executionTime = microtime(true) - $this->benchmarkStartTime;
        $memoryUsage = round((memory_get_usage() - $this->benchmarkStartMemory) / 1024 / 1024, 2);
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

            $rowDiff = $table && isset($this->startRowCount) ? $db->table($table)->count() - $this->startRowCount : 0;
        } catch (\Throwable $e) {
            $queriesCount = 0;
            $rowDiff = 0;
        }

        $db->disableQueryLog();

        $formattedTime = match (true) {
            $executionTime >= 60 => sprintf('%dm %ds', floor($executionTime / 60), $executionTime % 60),
            $executionTime >= 1 => round($executionTime, 2) . 's',
            default => round($executionTime * 1000) . 'ms',
        };

        $this->composeOutput($formattedTime, $memoryUsage, $queriesCount, $rowDiff);
    }

    private function composeOutput(string $formattedTime, string $memoryUsage, int $queriesCount, int $rowDiff): void
    {
        $is_in_console = app()->runningInConsole();
        $output_values = [];
        $output = '⚡';
        $this->addBlockToOutput($output, $output_values, 'TIME', $formattedTime, 'bright-blue', 'black', $is_in_console);
        $this->addBlockToOutput($output, $output_values, 'MEM', $memoryUsage, 'bright-green', 'black', $is_in_console);
        if ($queriesCount !== 0) {
            $this->addBlockToOutput($output, $output_values, 'SQL', number_format($queriesCount), 'bright-yellow', 'black', $is_in_console);
        }
        if ($rowDiff !== 0) {
            $this->addBlockToOutput($output, $output_values, 'ROWS', number_format($rowDiff), 'bright-magenta', 'black', $is_in_console);
        }
        $this->addBlockToOutput($output, $output_values, '', static::class, 'gray', 'black', $is_in_console);
        $output = sprintf($output, ...$output_values);

        if ($this instanceof Seeder) {
            $this->command->newLine();
            $this->command->line($output);
            $this->command->newLine();
        } elseif ($this instanceof \Illuminate\Console\Command && $this->output !== null) {
            $this->newLine();
            $this->line($output);
            $this->newLine();
        } else {
            Log::debug($output);
        }
    }

    private function addBlockToOutput(string &$output, array &$outputValues, string $blockName, mixed $blockValue, string $blockBgColor, string $blockFgColor, bool $isInConsole): void
    {
        if ($blockName !== '' && $blockName !== '0') {
            $blockName .= ' ';
        }
        $output .= $isInConsole ? " <bg=$blockBgColor;fg=$blockFgColor> $blockName%s </>" : " $blockName%s";
        $outputValues[] = $blockValue;
    }
}
