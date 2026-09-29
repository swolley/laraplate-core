<?php

declare(strict_types=1);

namespace Modules\Core\Services;

/**
 * Outcome of one setting action: the command line that ran, and its exit code and output
 * when it ran inside the request.
 */
final readonly class SettingActionResult
{
    private function __construct(
        public string $commandLine,
        public bool $queued,
        public int $exitCode,
        public string $output,
    ) {}

    public static function ran(string $commandLine, int $exitCode, string $output): self
    {
        return new self($commandLine, false, $exitCode, $output);
    }

    public static function queued(string $commandLine): self
    {
        return new self($commandLine, true, 0, '');
    }

    public function succeeded(): bool
    {
        return $this->exitCode === 0;
    }
}
