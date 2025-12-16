<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\CronJob;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->cronJob = CronJob::factory()->create();
});

it('can be created with factory', function (): void {
    expect($this->cronJob)->toBeInstanceOf(CronJob::class);
    expect($this->cronJob->id)->not->toBeNull();
});

it('has fillable attributes', function (): void {
    $cronJobData = [
        'name' => 'Test Cron Job',
        'command' => 'test:command',
        'parameters' => '{"param1": "value1"}',
        'schedule' => '0 0 * * *',
        'description' => 'Test cron job description',
        'is_active' => true,
    ];

    $cronJob = CronJob::create($cronJobData);

    expectModelAttributes($cronJob, [
        'name' => 'Test Cron Job',
        'command' => 'test:command',
        'parameters' => '{"param1": "value1"}',
        'schedule' => '0 0 * * *',
        'description' => 'Test cron job description',
        'is_active' => true,
    ]);
});

it('has default parameters as empty json', function (): void {
    $cronJob = CronJob::factory()->create();

    expect($cronJob->parameters)->toBe('{}');
});

it('has validation rules for creation', function (): void {
    $rules = $this->cronJob->getRules();

    expect($rules['create']['name'])->toContain('required', 'string', 'max:255');
    expect($rules['create']['command'])->toContain('required', 'string', 'max:255');
    expect($rules['create']['schedule'])->toContain('required', 'string');
    expect($rules['create']['is_active'])->toContain('boolean');
});

it('has validation rules for update', function (): void {
    $rules = $this->cronJob->getRules();

    expect($rules['update']['name'])->toContain('sometimes', 'string', 'max:255');
    expect($rules['update']['command'])->toContain('sometimes', 'string', 'max:255');
    expect($rules['update']['schedule'])->toContain('sometimes', 'string');
    expect($rules['update']['is_active'])->toContain('sometimes', 'boolean');
});

it('validates cron expression format', function (): void {
    expect(fn () => CronJob::create([
        'name' => 'Test Job',
        'command' => 'test:command',
        'schedule' => 'invalid-cron',
        'is_active' => true,
    ]))->toThrow(ValidationException::class);

    expect(fn () => CronJob::create([
        'name' => 'Test Job',
        'command' => 'test:command',
        'schedule' => '0 0 * * *',
        'is_active' => true,
    ]))->not->toThrow(ValidationException::class);
});

it('has soft deletes trait', function (): void {
    $this->cronJob->delete();

    expect($this->cronJob->trashed())->toBeTrue();
    expect(CronJob::withTrashed()->find($this->cronJob->id))->not->toBeNull();
});

it('has versions trait', function (): void {
    expect(method_exists($this->cronJob, 'versions'))->toBeTrue();
    expect(method_exists($this->cronJob, 'createVersion'))->toBeTrue();
});

it('has locks trait', function (): void {
    expect(method_exists($this->cronJob, 'lock'))->toBeTrue();
    expect(method_exists($this->cronJob, 'unlock'))->toBeTrue();
});

it('has validations trait', function (): void {
    expect(method_exists($this->cronJob, 'getRules'))->toBeTrue();
});

it('can be created with specific attributes', function (): void {
    $cronJobData = [
        'name' => 'Custom Cron Job',
        'command' => 'custom:command',
        'parameters' => '{"custom": "param"}',
        'schedule' => '*/5 * * * *',
        'description' => 'Custom cron job',
        'is_active' => false,
    ];

    $cronJob = CronJob::create($cronJobData);

    expectModelAttributes($cronJob, [
        'name' => 'Custom Cron Job',
        'command' => 'custom:command',
        'parameters' => '{"custom": "param"}',
        'schedule' => '*/5 * * * *',
        'description' => 'Custom cron job',
        'is_active' => false,
    ]);
});

it('can be found by name', function (): void {
    $cronJob = CronJob::factory()->create(['name' => 'unique-cron-job']);

    $foundCronJob = CronJob::where('name', 'unique-cron-job')->first();

    expect($foundCronJob->id)->toBe($cronJob->id);
});

it('can be found by command', function (): void {
    $cronJob = CronJob::factory()->create(['command' => 'unique:command']);

    $foundCronJob = CronJob::where('command', 'unique:command')->first();

    expect($foundCronJob->id)->toBe($cronJob->id);
});

it('can be found by active status', function (): void {
    $activeCronJob = CronJob::factory()->create(['is_active' => true]);
    $inactiveCronJob = CronJob::factory()->create(['is_active' => false]);

    $activeCronJobs = CronJob::where('is_active', true)->get();
    $inactiveCronJobs = CronJob::where('is_active', false)->get();

    expect($activeCronJobs)->toHaveCount(1);
    expect($inactiveCronJobs)->toHaveCount(1);
    expect($activeCronJobs->first()->id)->toBe($activeCronJob->id);
    expect($inactiveCronJobs->first()->id)->toBe($inactiveCronJob->id);
});

it('can be found by schedule', function (): void {
    $cronJob = CronJob::factory()->create(['schedule' => '0 0 * * *']);

    $foundCronJob = CronJob::where('schedule', '0 0 * * *')->first();

    expect($foundCronJob->id)->toBe($cronJob->id);
});

it('has proper timestamps', function (): void {
    $cronJob = CronJob::factory()->create();

    expect($cronJob->created_at)->toBeInstanceOf(Carbon\Carbon::class);
    expect($cronJob->updated_at)->toBeInstanceOf(Carbon\Carbon::class);
});

it('can be serialized to array', function (): void {
    $cronJob = CronJob::factory()->create([
        'name' => 'Test Job',
        'command' => 'test:command',
        'is_active' => true,
    ]);
    $cronJobArray = $cronJob->toArray();

    expect($cronJobArray)->toHaveKey('id');
    expect($cronJobArray)->toHaveKey('name');
    expect($cronJobArray)->toHaveKey('command');
    expect($cronJobArray)->toHaveKey('schedule');
    expect($cronJobArray)->toHaveKey('is_active');
    expect($cronJobArray)->toHaveKey('created_at');
    expect($cronJobArray)->toHaveKey('updated_at');
    expect($cronJobArray['name'])->toBe('Test Job');
    expect($cronJobArray['command'])->toBe('test:command');
    expect($cronJobArray['is_active'])->toBeTrue();
});

it('can be restored after soft delete', function (): void {
    $cronJob = CronJob::factory()->create();
    $cronJob->delete();

    expect($cronJob->trashed())->toBeTrue();

    $cronJob->restore();

    expect($cronJob->trashed())->toBeFalse();
});

it('can be permanently deleted', function (): void {
    $cronJob = CronJob::factory()->create();
    $cronJobId = $cronJob->id;

    $cronJob->forceDelete();

    expect(CronJob::withTrashed()->find($cronJobId))->toBeNull();
});
