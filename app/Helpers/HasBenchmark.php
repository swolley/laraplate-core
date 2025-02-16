<?php

namespace Modules\Core\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait HasBenchmark
{
    protected function startBenchmark(?string $table = null): void
    {
        $this->benchmarkStartTime = microtime(true);
        $this->benchmarkStartMemory = memory_get_usage();
        if ($table) {
            $this->startRowCount = DB::table($table)->count();
            DB::enableQueryLog();
            $this->startQueries = DB::select("SHOW SESSION STATUS LIKE 'Questions'")[0]->Value;
        }
    }

    protected function endBenchmark(?string $table = null): void
    {
        $executionTime = microtime(true) - $this->benchmarkStartTime;
        $memoryUsage = round((memory_get_usage() - $this->benchmarkStartMemory) / 1024 / 1024, 2);
        if ($table && isset($this->startQueries) && isset($this->startRowCount)) {
            $queriesCount = DB::select("SHOW SESSION STATUS LIKE 'Questions'")[0]->Value - $this->startQueries - 1; // Subtract the Questions query itself
            // Get row count after we've stopped tracking queries
            $rowDiff = DB::table($table)->count() - $this->startRowCount;
        } else {
            $queriesCount = 0;
            $rowDiff = 0;
        }


        $formattedTime = match (true) {
            $executionTime >= 60 => sprintf('%dm %ds', floor($executionTime / 60), $executionTime % 60),
            $executionTime >= 1 => round($executionTime, 2).'s',
            default => round($executionTime * 1000).'ms',
        };

        $is_in_console = app()->runningInConsole();

        $output = '';
        if ($table && isset($this->startQueries) && isset($this->startRowCount)) {
            $output = $is_in_console 
                ? sprintf(
                    '⚡ <bg=bright-blue;fg=black> TIME: %s </> <bg=bright-green;fg=black> MEM: %sMB </> <bg=bright-yellow;fg=black> SQL: %s </> <bg=bright-magenta;fg=black> ROWS: %s </>',
                    $formattedTime,
                    $memoryUsage,
                    number_format($queriesCount),
                    number_format($rowDiff)
                    )
                : sprintf(
                    'TIME: %s MEM: %sMB SQL: %s ROWS: %s',
                    $formattedTime,
                    $memoryUsage,
                    number_format($queriesCount),
                    number_format($rowDiff)
                );
        } else {
            $output = $is_in_console 
                ? sprintf(
                    '⚡ <bg=bright-blue;fg=black> TIME: %s </> <bg=bright-green;fg=black> MEM: %sMB </>',
                    $formattedTime,
                    $memoryUsage
                )
                : sprintf(
                    'TIME: %s MEM: %sMB',
                    $formattedTime,
                    $memoryUsage
                );
        }
        if ($is_in_console) {
            $this->newLine();
            $this->line($output);
            $this->newLine();
        } else {
            Log::debug($output);
        }
    }
}