<?php

declare(strict_types=1);

namespace Modules\Core\Performance;

use Modules\Core\Contracts\BootSampler;
use Symfony\Component\Process\Process;

/**
 * Samples boot time by spawning fresh `artisan perf:boot --child` processes,
 * each of which reports its own boot duration. Using independent processes is
 * the only way to observe a genuine cold boot repeatedly.
 */
final class SubprocessBootSampler implements BootSampler
{
    public function sample(int $runs): array
    {
        $samples = [];

        for ($i = 0; $i < $runs; $i++) {
            $process = new Process(
                [PHP_BINARY, base_path('artisan'), 'perf:boot', '--child'],
                base_path(),
                timeout: 120.0,
            );
            $process->run();

            if (preg_match('/BOOT_MS=([\d.]+)/', $process->getOutput(), $matches) === 1) {
                $samples[] = (float) $matches[1];
            }
        }

        return $samples;
    }
}
