<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Benchmark;

use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Modules\Core\Helpers\HasBenchmark;

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
