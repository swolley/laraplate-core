<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Logging\GelfAdditionalInfoProcessor;
use Modules\Core\Logging\GelfErrorFingerprintResolver;
use Modules\Core\Models\User;
use Modules\Core\Tests\Fixtures\GelfProcessorInvoker;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * @return array{version: string}
 */
function composerPackage(string $path): array
{
    return json_decode((string) file_get_contents($path), true) ?? [];
}

it('adds module from the caller trace for core code', function (): void {
    $processor = new GelfAdditionalInfoProcessor('gelf');

    $record = GelfProcessorInvoker::invoke($processor, new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'gelf',
        level: Level::Error,
        message: 'Indexing failed',
        context: [],
        extra: [],
    ));

    expect($record->extra['app_name'])->toBe(config('app.name'))
        ->and($record->extra['module'])->toBe('Core')
        ->and($record->extra['application_version'])->toBe(version())
        ->and($record->extra['module_version'])->toBe(composerPackage(module_path('Core', 'composer.json'))['version'])
        ->and($record->extra['error_fingerprint'])->toHaveLength(16)
        ->and($record->extra)->not->toHaveKeys(['request_url', 'request_name', 'user_id']);
});

it('adds module from exception file when present in context', function (): void {
    $processor = new GelfAdditionalInfoProcessor('gelf');

    $record = $processor(new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'gelf',
        level: Level::Error,
        message: 'Job failed',
        context: [
            'exception' => [
                'file' => base_path('Modules/AI/app/Jobs/GenerateEmbeddingsJob.php'),
            ],
        ],
        extra: [],
    ));

    expect($record->extra['module'])->toBe('AI')
        ->and($record->extra['application_version'])->toBe(version())
        ->and($record->extra['module_version'])->toBe(composerPackage(module_path('AI', 'composer.json'))['version']);
});

it('adds module App for application paths', function (): void {
    $processor = new GelfAdditionalInfoProcessor('gelf');

    $record = $processor(new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'gelf',
        level: Level::Error,
        message: 'Controller failed',
        context: [
            'exception' => [
                'file' => base_path('app/Http/Controllers/ExampleController.php'),
            ],
        ],
        extra: [],
    ));

    expect($record->extra['module'])->toBe('App')
        ->and($record->extra['application_version'])->toBe(version())
        ->and($record->extra)->not->toHaveKey('module_version');
});

it('adds module from throwable exception instances', function (): void {
    $processor = new GelfAdditionalInfoProcessor('gelf');

    $record = $processor(new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'gelf',
        level: Level::Error,
        message: 'Command failed',
        context: [
            'exception' => new RuntimeException('failed'),
        ],
        extra: [],
    ));

    expect($record->extra['module'])->toBe('Core')
        ->and($record->extra['module_version'])->toBe(composerPackage(module_path('Core', 'composer.json'))['version']);
});

it('adds request url and route name during http requests', function (): void {
    Route::middleware('web')->get('/gelf-processor-test', function () {
        $processor = new GelfAdditionalInfoProcessor('gelf');

        return response()->json(
            $processor(new LogRecord(
                datetime: new DateTimeImmutable(),
                channel: 'gelf',
                level: Level::Error,
                message: 'Request failed',
                context: [],
                extra: [],
            ))->extra,
        );
    })->name('gelf.processor.test');

    $response = $this->get('/gelf-processor-test');

    $response->assertOk();

    expect($response->json('app_name'))->toBe(config('app.name'))
        ->and($response->json('request_url'))->toContain('/gelf-processor-test')
        ->and($response->json('request_name'))->toBe('gelf.processor.test')
        ->and($response->json())->not->toHaveKeys(['user', 'user_id']);
});

it('adds user id when the request has an authenticated session', function (): void {
    $user = User::factory()->create();

    Route::middleware('web')->get('/gelf-processor-auth-test', function () {
        $processor = new GelfAdditionalInfoProcessor('gelf');

        return response()->json(
            $processor(new LogRecord(
                datetime: new DateTimeImmutable(),
                channel: 'gelf',
                level: Level::Error,
                message: 'Authenticated request failed',
                context: [],
                extra: [],
            ))->extra,
        );
    })->name('gelf.processor.auth.test');

    $response = $this->actingAs($user)->get('/gelf-processor-auth-test');

    $response->assertOk();

    expect($response->json('user_id'))->toBe($user->id)
        ->and($response->json('error_fingerprint'))->toHaveLength(16)
        ->and($response->json())->not->toHaveKey('user');
});

it('keeps the same fingerprint for recurring errors with different user ids', function (): void {
    User::factory()->create();
    User::factory()->create();

    $job_file = base_path('Modules/AI/app/Jobs/GenerateEmbeddingsJob.php');
    $resolver = new GelfErrorFingerprintResolver;

    $fingerprint_a = $resolver->resolve(new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'gelf',
        level: Level::Error,
        message: 'Failed',
        context: [
            'exception' => [
                'class' => RuntimeException::class,
                'file' => $job_file,
                'line' => 42,
                'message' => 'User 1 missing',
            ],
        ],
        extra: [],
    ));

    $fingerprint_b = $resolver->resolve(new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'gelf',
        level: Level::Error,
        message: 'Failed',
        context: [
            'exception' => [
                'class' => RuntimeException::class,
                'file' => $job_file,
                'line' => 42,
                'message' => 'User 2 missing',
            ],
        ],
        extra: [],
    ));

    expect($fingerprint_a)->toBe($fingerprint_b);
});
