<?php

declare(strict_types=1);

use Modules\Core\Models\User;
use Modules\Core\Models\Version;
use Modules\Core\Tests\LaravelTestCase;
use Overtrue\LaravelVersionable\VersionStrategy;

uses(LaravelTestCase::class);

it('persists version_strategy on the version row', function (): void {
    $user = User::factory()->create(['name' => 'Alice']);
    $refreshed = User::query()->withoutGlobalScopes()->findOrFail($user->getKey());
    $refreshed->versionStrategy = VersionStrategy::SNAPSHOT;

    $version = Version::createForModel($refreshed, [], null, VersionStrategy::SNAPSHOT);

    expect($version->version_strategy)->toBe(VersionStrategy::SNAPSHOT);
});

it('creates a snapshot version row with SNAPSHOT strategy when async is off', function (): void {
    $user = User::factory()->create(['name' => 'Bob']);
    $user->versionStrategy = VersionStrategy::DIFF;

    $reflection = new \ReflectionClass($user);
    $async_prop = $reflection->getProperty('asyncVersioning');
    $async_prop->setAccessible(true);
    $async_prop->setValue($user, false);

    $version = $user->createSnapshotVersion();

    expect($version)->not->toBeNull()
        ->and($version->version_strategy)->toBe(VersionStrategy::SNAPSHOT);
});
