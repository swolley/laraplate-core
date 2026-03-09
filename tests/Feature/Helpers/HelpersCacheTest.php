<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Helpers\HelpersCache;
use Modules\Core\Models\User;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    HelpersCache::clearAll();
});

it('models() returns a non-empty list of model class strings', function (): void {
    HelpersCache::setModels('active', [User::class]);

    $models = models();

    expect($models)->toBeArray()->not->toBeEmpty()
        ->and($models[0])->toBeString()->toContain('\\');
});

it('models() returns the same result on repeated calls (memoized)', function (): void {
    HelpersCache::setModels('active', [User::class]);

    $first = models();
    $second = models();

    expect($first)->toBe($second);
});

it('models() caches results in HelpersCache', function (): void {
    expect(HelpersCache::getModels('active'))->toBeNull();

    models();

    expect(HelpersCache::getModels('active'))->not->toBeNull()->toBeArray();
});

it('models() filters by module when $onlyModule is provided', function (): void {
    HelpersCache::setModels('active', [User::class]);

    $all = models();
    $core_only = models(onlyModule: 'Core');

    expect($core_only)->not->toBeEmpty();

    foreach ($core_only as $class) {
        expect($class)->toStartWith('Modules\\Core\\');
    }

    expect(count($core_only))->toBeLessThanOrEqual(count($all));
});

it('models() filters with custom callable', function (): void {
    HelpersCache::setModels('active', [User::class]);

    $all = models();
    $filtered = models(filter: fn (string $class): bool => str_contains($class, 'User'));

    expect($filtered)->not->toBeEmpty();
    expect(count($filtered))->toBeLessThanOrEqual(count($all));

    foreach ($filtered as $class) {
        expect($class)->toContain('User');
    }
});

it('connections() returns a non-empty list of driver strings', function (): void {
    HelpersCache::setConnections('active', ['sqlite']);

    $connections = connections();

    expect($connections)->toBeArray()->not->toBeEmpty();
});

it('connections() caches results in HelpersCache', function (): void {
    expect(HelpersCache::getConnections('active'))->toBeNull();

    connections();

    expect(HelpersCache::getConnections('active'))->not->toBeNull()->toBeArray();
});

it('HelpersCache::clearAll resets both models and connections', function (): void {
    models();
    connections();

    expect(HelpersCache::getModels('active'))->not->toBeNull();
    expect(HelpersCache::getConnections('active'))->not->toBeNull();

    HelpersCache::clearAll();

    expect(HelpersCache::getModels('active'))->toBeNull();
    expect(HelpersCache::getConnections('active'))->toBeNull();
});

it('HelpersCache::clearModels only clears model cache', function (): void {
    models();
    connections();

    HelpersCache::clearModels();

    expect(HelpersCache::getModels('active'))->toBeNull();
    expect(HelpersCache::getConnections('active'))->not->toBeNull();
});
