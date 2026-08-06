<?php

declare(strict_types=1);

namespace Modules\Core\Console\Concerns;

use Modules\Core\Helpers\BatchSeeder;
use Symfony\Component\Console\Input\InputOption;

/**
 * Shared wiring for the `--micro` / `--min` / `--mid` / `--max` dev-seed volume flags used by
 * both {@see \Modules\Core\Console\SeedCommand} and
 * {@see \Modules\Core\Console\ModuleSeedCommand}.
 *
 * The resolved factor is published on the container (not read off the console
 * command) because `db:seed` is a shared container instance whose input the
 * nested `module:seed -> db:seed` calls the dev seeders make would otherwise
 * overwrite before {@see BatchSeeder} reads it.
 */
trait ResolvesDevSeedScale
{
    /**
     * Dev-seed volume multipliers by scale flag, in precedence order: the first
     * flag present wins. Absent flags fall through to the full (max) target.
     */
    private const array DEV_SEED_SCALE_FACTORS = ['micro' => 0.01, 'min' => 0.1, 'mid' => 0.5, 'max' => 1.0];

    /**
     * @return list<array{0: string, 1: string|null, 2: int, 3: string}>
     */
    protected function devSeedScaleOptions(): array
    {
        return [
            ['micro', null, InputOption::VALUE_NONE, 'Scale dev record volume to 1% of the target count'],
            ['min', null, InputOption::VALUE_NONE, 'Scale dev record volume to 10% of the target count'],
            ['mid', null, InputOption::VALUE_NONE, 'Scale dev record volume to 50% of the target count'],
            ['max', null, InputOption::VALUE_NONE, 'Scale dev record volume to 100% of the target count (default)'],
        ];
    }

    /**
     * Resolve the dev-seed volume multiplier from the scale flags. Precedence
     * follows {@see self::DEV_SEED_SCALE_FACTORS}; with no flag set the full
     * (max) target is used.
     */
    protected function resolveDevSeedScale(): float
    {
        foreach (self::DEV_SEED_SCALE_FACTORS as $option => $factor) {
            if ($this->option($option)) {
                return $factor;
            }
        }

        return self::DEV_SEED_SCALE_FACTORS['micro'];
    }

    /**
     * Publish the resolved scale for the duration of $callback, clearing it
     * afterwards so it never leaks into a later run.
     *
     * @param  callable(): int  $callback
     */
    protected function withPublishedDevSeedScale(callable $callback): int
    {
        $this->laravel->instance(BatchSeeder::SCALE_CONTAINER_KEY, $this->resolveDevSeedScale());

        try {
            return $callback();
        } finally {
            $this->laravel->forgetInstance(BatchSeeder::SCALE_CONTAINER_KEY);
        }
    }
}
