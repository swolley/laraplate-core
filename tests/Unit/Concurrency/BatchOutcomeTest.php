<?php

declare(strict_types=1);

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use Modules\Core\Concurrency\BatchOutcome;

it('builds a successful outcome', function (): void {
    $outcome = BatchOutcome::success(taskId: 'task_1', units: 50, duration: 0.42, output: 'ok');

    expect($outcome->success)->toBeTrue();
    expect($outcome->taskId)->toBe('task_1');
    expect($outcome->unitsProcessed)->toBe(50);
    expect($outcome->duration)->toBe(0.42);
    expect($outcome->output)->toBe('ok');
    expect($outcome->error)->toBeNull();
});

it('builds a failure outcome from a generic throwable', function (): void {
    $exception = new \RuntimeException('boom');

    $outcome = BatchOutcome::failure(taskId: 'task_x', units: 0, duration: 0.1, e: $exception);

    expect($outcome->success)->toBeFalse();
    expect($outcome->taskId)->toBe('task_x');
    expect($outcome->error)->toBeArray();
    expect($outcome->error['message'])->toBe('boom');
    expect($outcome->error['class'])->toBe(\RuntimeException::class);
    expect($outcome->error)->toHaveKeys(['file', 'line', 'trace']);
});

it('formats validation errors with field, value and messages', function (): void {
    $loader = new ArrayLoader();
    $loader->addMessages('en', 'validation', [
        'email' => 'must be a valid email address',
    ]);

    $translator = new Translator($loader, 'en');
    $factory = new Factory($translator);

    $validator = $factory->make(
        ['email' => 'not-an-email'],
        ['email' => 'email'],
    );

    expect($validator->fails())->toBeTrue();

    $exception = new ValidationException($validator);

    $outcome = BatchOutcome::failure(taskId: 'val', units: 0, duration: 0.0, e: $exception);

    expect($outcome->error['message'])->toContain('Validation failed for field "email"');
    expect($outcome->error['message'])->toContain('must be a valid email address');
    expect($outcome->error['message'])->toContain('not-an-email');
});
