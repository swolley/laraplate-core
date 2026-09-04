<?php

declare(strict_types=1);

namespace Modules\Core\Locking\Console;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Locking\Locked;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Overrides\Command;
use Override;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Throwable;

/**
 * Clears the lock columns of records whose deadline has passed.
 *
 * This is housekeeping, never correctness. Expiry is evaluated on every read, by
 * {@see HasLocks::isLocked()} and by the matching query scope, so a lapsed lock is already free to
 * everyone whether or not this command has run. All it buys is that rows stop carrying dead
 * coordination metadata, which keeps the `locked_until` index small and the data readable.
 *
 * A missed run therefore changes nothing, which is why the work is bounded: each model gives up at
 * most `--limit` rows per pass and the next run picks up the rest.
 */
final class ModelLockSweepCommand extends Command
{
    #[Override]
    protected $signature = 'model:lock-sweep {--limit=1000 : Maximum rows cleared per model in one pass}';

    #[Override]
    protected $description = 'Clear locks whose deadline has passed. <fg=green>(⚡ Modules\Core)</fg=green>';

    /**
     * Tables already swept in this run, keyed by connection and table.
     *
     * Model discovery can reach the same table through more than one class, and sweeping it twice
     * would silently clear twice the requested limit.
     *
     * @var array<string,true>
     */
    private array $swept = [];

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $locked = new Locked();
        $total = 0;
        $this->swept = [];

        foreach (models() as $model) {
            $cleared = $this->sweepModel($model, $locked, $limit);

            if ($cleared > 0) {
                $total += $cleared;
                $this->line(sprintf('%s: released %d expired lock(s)', $model, $cleared));
            }
        }

        $this->output->success(sprintf('Released %d expired lock(s)', $total));

        return BaseCommand::SUCCESS;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function sweepModel(string $model, Locked $locked, int $limit): int
    {
        if (! class_exists($model)) {
            return 0;
        }

        try {
            $instance = new $model();
        } catch (Throwable) {
            // Abstract models and models needing constructor arguments are not lockable rows.
            return 0;
        }

        if (! $instance instanceof Model || ! class_uses_trait($instance, HasLocks::class)) {
            return 0;
        }

        $connection = $instance->getConnection();
        $fingerprint = $connection->getName() . '.' . $instance->getTable();

        if (isset($this->swept[$fingerprint])) {
            return 0;
        }

        $until_column = $locked->lockedUntilColumn();

        if (! $connection->getSchemaBuilder()->hasColumn($instance->getTable(), $until_column)) {
            return 0;
        }

        $this->swept[$fingerprint] = true;

        // Two statements rather than one bounded UPDATE: `UPDATE ... LIMIT` is MySQL-only, and the
        // repo prefers portable query-builder code.
        $expired_keys = $instance->newQueryWithoutScopes()
            ->whereNotNull($until_column)
            ->where($until_column, '<=', now())
            ->limit($limit)
            ->pluck($instance->getKeyName());

        if ($expired_keys->isEmpty()) {
            return 0;
        }

        // Straight to the query builder: releasing a lapsed lock must no more touch `updated_at` or
        // `lock_version` than taking one does.
        return $instance->newQueryWithoutScopes()
            ->whereIn($instance->getKeyName(), $expired_keys)
            ->toBase()
            ->update([
                $locked->lockedAtColumn() => null,
                $locked->lockedByColumn() => null,
                $until_column => null,
            ]);
    }
}
