<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Illuminate\Queue\Console\MonitorCommand as BaseMonitorCommand;
use Illuminate\Support\Collection;
use Modules\Core\Console\Concerns\HasBenchmark;
use Override;

/**
 * Adds `--all` to `queue:monitor` so every queue Horizon is configured to
 * process can be checked in one call, instead of listing `connection:queue`
 * pairs by hand. Everything else delegates to the framework command.
 */
final class QueueMonitorCommand extends BaseMonitorCommand
{
    use HasBenchmark;

    /**
     * @var string
     */
    protected $signature = 'queue:monitor
                            {queues? : The names of the queues to monitor (connection:queue, comma-separated)}
                            {--max=1000 : The maximum number of jobs that can be on the queue before an event is dispatched}
                            {--json : Output the queue size as JSON}
                            {--all : Monitor every queue Horizon is configured to process}';

    /**
     * @var string
     */
    protected $description = 'Monitor queue sizes; --all covers every Horizon queue <fg=green>(⚡ Modules\Core)</fg=green>';

    #[Override]
    public function handle()
    {
        $benchmark = ! app()->runningUnitTests();

        if ($benchmark) {
            $this->startBenchmark();
        }

        try {
            if ($this->option('all')) {
                $this->input->setArgument('queues', $this->configuredQueues());
            }

            $queues = $this->argument('queues');

            if (! is_string($queues) || mb_trim($queues) === '') {
                $this->components->error('Provide queue names (connection:queue,...) or pass --all.');

                return self::FAILURE;
            }

            return parent::handle();
        } finally {
            if ($benchmark) {
                $this->endBenchmark();
            }
        }
    }

    /**
     * The default connection prefixed to every queue Horizon processes
     * (union of `horizon.defaults` and the active environment block), falling
     * back to the default connection's own queue when Horizon has none.
     */
    public function configuredQueues(): string
    {
        $connection = (string) config('queue.default', 'redis');

        $queues = (new Collection(config('horizon.defaults', [])))->pluck('queue')
            ->merge((new Collection(config('horizon.environments.' . app()->environment(), [])))->pluck('queue'))
            ->flatten()
            ->filter(fn ($queue): bool => is_string($queue) && $queue !== '')
            ->unique()
            ->values();

        if ($queues->isEmpty()) {
            $queues = new Collection([(string) config("queue.connections.{$connection}.queue", 'default')]);
        }

        return $queues->map(fn (string $queue): string => "{$connection}:{$queue}")->implode(',');
    }
}
