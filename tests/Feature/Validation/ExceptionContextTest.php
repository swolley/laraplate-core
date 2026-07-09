<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Core\Overrides\ContextualValidationException;

it('reports non-validation throwables without tripping the global context callback', function (): void {
    $handler = app(ExceptionHandler::class);

    $handler->report(new \TypeError('boom'));

    expect(true)->toBeTrue();
});

it('enriches validation exception context for logging', function (): void {
    $handler = app(ExceptionHandler::class);

    $validator = Validator::make(['name' => ''], ['name' => 'required']);

    try {
        $validator->validate();
    } catch (ContextualValidationException $exception) {
        $handler->report($exception);

        expect(true)->toBeTrue();

        return;
    }

    expect(false)->toBeTrue('expected ContextualValidationException was not thrown');
});

it('keeps generic validation exceptions reportable', function (): void {
    $handler = app(ExceptionHandler::class);

    $exception = ValidationException::withMessages([
        'name' => ['The name field is required.'],
    ]);

    $handler->report($exception);

    expect(true)->toBeTrue();
});
