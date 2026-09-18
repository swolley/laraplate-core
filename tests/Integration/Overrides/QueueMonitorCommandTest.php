<?php

declare(strict_types=1);

use Illuminate\Queue\Console\MonitorCommand as LaravelQueueMonitorCommand;
use Modules\Core\Overrides\QueueMonitorCommand;

function queue_monitor_command(): QueueMonitorCommand
{
    /** @var QueueMonitorCommand $command */
    return app(LaravelQueueMonitorCommand::class);
}

it('overrides the framework queue:monitor command', function (): void {
    expect(queue_monitor_command())->toBeInstanceOf(QueueMonitorCommand::class);
});

it('resolves every Horizon queue prefixed with the default connection', function (): void {
    config()->set('queue.default', 'redis');
    config()->set('horizon.defaults', [
        'supervisor-embeddings' => ['queue' => ['embeddings']],
        'supervisor-indexing' => ['queue' => ['indexing']],
    ]);

    expect(queue_monitor_command()->configuredQueues())->toBe('redis:embeddings,redis:indexing');
});

it('keeps default queues when the active environment block nulls them out', function (): void {
    config()->set('queue.default', 'redis');
    config()->set('horizon.defaults', [
        'supervisor-embeddings' => ['queue' => ['embeddings']],
        'supervisor-indexing' => ['queue' => ['indexing']],
    ]);
    config()->set('horizon.environments.' . app()->environment(), [
        'supervisor-embeddings' => ['queue' => null],
        'supervisor-indexing' => ['queue' => null],
    ]);

    expect(queue_monitor_command()->configuredQueues())->toBe('redis:embeddings,redis:indexing');
});

it('falls back to the default connection queue when no Horizon queues are configured', function (): void {
    config()->set('queue.default', 'redis');
    config()->set('horizon.defaults', []);
    config()->set('horizon.environments.' . app()->environment(), []);
    config()->set('queue.connections.redis.queue', 'default');

    expect(queue_monitor_command()->configuredQueues())->toBe('redis:default');
});
