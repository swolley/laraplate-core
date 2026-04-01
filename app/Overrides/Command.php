<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Illuminate\Console\Command as BaseCommand;
use Modules\Core\Helpers\HasBenchmark;
use Throwable;

class Command extends BaseCommand
{
    use HasBenchmark;

    public function __construct()
    {
        parent::__construct();

        if (! $this->isRunningUnitTests() && $this->isLaunchedManually()) {
            $this->startBenchmark();
        }
    }

    public function __destruct()
    {
        if ($this->benchmarkStartTime !== null && ! $this->isRunningUnitTests() && app()->bound('config')) {
            $this->endBenchmark();
        }
    }

    private function isRunningUnitTests(): bool
    {
        if (! function_exists('app')) {
            return false;
        }

        try {
            return app()->runningUnitTests();
        } catch (Throwable) {
            return false;
        }
    }

    private function isRunningInConsole(): bool
    {
        return app()->runningInConsole();
    }

    private function isLaunchedManually(): bool
    {
        if (! $this->isRunningInConsole()) {
            return false;
        }

        $interactive = $this->input?->isInteractive() ?? false;

        $has_tty = false;

        if (function_exists('posix_isatty') && defined('STDIN')) {
            $has_tty = posix_isatty(STDIN);
        }

        return $interactive || $has_tty;
    }
}
