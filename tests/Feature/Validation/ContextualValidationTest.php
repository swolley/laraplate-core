<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Modules\Core\Casts\CrudExecutor;
use Modules\Core\Concurrency\BatchOutcome;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Models\CronJob;
use Modules\Core\Overrides\ContextualValidationException;
use Modules\Core\Overrides\ContextualValidator;

it('resolves contextual validator instances from the factory', function (): void {
    $validator = Validator::make(['name' => 'test'], ['name' => 'required']);

    expect($validator)->toBeInstanceOf(ContextualValidator::class);
    expect($validator->validate())->toBe(['name' => 'test']);
});

it('uses contextual validation exception by default for all validators', function (): void {
    try {
        Validator::make(['name' => ''], ['name' => 'required'])->validate();

        expect(false)->toBeTrue('expected ContextualValidationException was not thrown');
    } catch (ContextualValidationException $exception) {
        expect($exception->getMessage())->toContain('"name"');
    }
});

it('translates default validation messages from core lang files', function (): void {
    app()->setLocale('it');

    $validator = Validator::make(['name' => ''], ['name' => 'required']);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('name'))->toContain('richiesto');
});

it('describes every failed field in the exception message', function (): void {
    try {
        Validator::make(
            ['name' => '', 'email' => 'not-an-email'],
            ['name' => 'required', 'email' => 'email'],
        )->validate();

        expect(false)->toBeTrue('expected ContextualValidationException was not thrown');
    } catch (ContextualValidationException $exception) {
        expect($exception->getMessage())->toContain('"name"');
        expect($exception->getMessage())->toContain('"email"');
    }
});

it('attaches model validation context to contextual validation exceptions', function (): void {
    $unique_name = 'invalid-cron-context-' . uniqid();

    try {
        CronJob::create([
            'name' => $unique_name,
            'command' => 'test:command',
            'schedule' => 'invalid-cron',
        ]);

        expect(false)->toBeTrue('expected ContextualValidationException was not thrown');
    } catch (ContextualValidationException $exception) {
        expect($exception->context())->toMatchArray([
            'entity' => CoreTables::CronJobs->value,
            'model' => CronJob::class,
            'operation' => CrudExecutor::INSERT,
        ]);
        expect($exception->context())->not->toHaveKey('id');
        expect($exception->getMessage())->toContain('entity=' . CoreTables::CronJobs->value);
        expect($exception->getMessage())->toContain('model=' . CronJob::class);
        expect($exception->getMessage())->toContain('operation=' . CrudExecutor::INSERT);
    }
});

it('includes validation context in batch outcome error messages', function (): void {
    $validator = Validator::make(['name' => ''], ['name' => 'required']);

    expect($validator)->toBeInstanceOf(ContextualValidator::class);

    $validator->withLogContext([
        'entity' => CoreTables::CronJobs->value,
        'model' => CronJob::class,
        'operation' => CrudExecutor::INSERT,
    ]);
    $validator->setException(ContextualValidationException::class);

    try {
        $validator->validate();
        expect(false)->toBeTrue('expected ContextualValidationException was not thrown');
    } catch (ContextualValidationException $exception) {
        $outcome = BatchOutcome::failure('task-1', 0, 0.0, $exception);

        expect($outcome->error['message'] ?? '')->toContain('entity=' . CoreTables::CronJobs->value);
        expect($outcome->error['message'] ?? '')->toContain('model=' . CronJob::class);
        expect($outcome->error['message'] ?? '')->toContain('operation=' . CrudExecutor::INSERT);
    }
});

it('keeps batch outcome messages unchanged for generic validation exceptions', function (): void {
    $exception = Illuminate\Validation\ValidationException::withMessages([
        'name' => ['The name field is required.'],
    ]);

    $outcome = BatchOutcome::failure('task-1', 0, 0.0, $exception);

    expect($outcome->error['message'] ?? '')->toBe(
        'Validation failed for field "name": The name field is required. (value: N/A)',
    );
});

it('resolves update rules when validating with the crud executor update operation', function (): void {
    $cron_job = CronJob::factory()->create();

    $update_rules = $cron_job->getOperationRules(CrudExecutor::UPDATE);
    $legacy_save_rules = $cron_job->getOperationRules('save');

    expect($update_rules)->toHaveKey('name');
    expect($legacy_save_rules)->toHaveKey('name');
    expect(array_keys($legacy_save_rules))->toBe(array_keys($update_rules));
});

it('applies update validation rules during model update', function (): void {
    $cron_job = CronJob::factory()->create();

    try {
        $cron_job->update(['schedule' => 'invalid-cron']);

        expect(false)->toBeTrue('expected ContextualValidationException was not thrown');
    } catch (ContextualValidationException $exception) {
        expect($exception->context())->toMatchArray([
            'entity' => CoreTables::CronJobs->value,
            'model' => CronJob::class,
            'operation' => CrudExecutor::UPDATE,
            'id' => $cron_job->id,
        ]);
        expect($exception->errors())->toHaveKey('schedule');
    }
});
