<?php

declare(strict_types=1);

use Modules\Core\Concurrency\BatchTask;
use Modules\Core\Tests\Fixtures\FixtureInvokableTask;

it('executes a closure task', function (): void {
    $task = new BatchTask(id: 't1', units: 1, run: fn (int $x): int => $x * 2, args: [21]);

    expect($task->execute())->toBe(42);
});

it('executes an invokable class resolved from the container', function (): void {
    $task = new BatchTask(id: 't2', units: 1, run: FixtureInvokableTask::class, args: [2, 3]);

    expect($task->execute())->toBe(5);
});

it('passes no arguments by default', function (): void {
    $task = new BatchTask(id: 't3', units: 1, run: fn (): string => 'hello');

    expect($task->execute())->toBe('hello');
});
