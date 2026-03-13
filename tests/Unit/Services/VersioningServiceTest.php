<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Version;
use Modules\Core\Services\VersioningService;
use Modules\Core\Tests\LaravelTestCase;
use Modules\Core\Tests\Stubs\FakeVersionedModel;

uses(LaravelTestCase::class);

it('can be instantiated', function (): void {
    $service = new VersioningService();

    expect($service)->toBeInstanceOf(VersioningService::class);
});

it('creates a version with basic attributes and no extras', function (): void {
    config()->set('versionable.version_model', Version::class);

    $service = new VersioningService();

    $version = $service->createVersion(
        FakeVersionedModel::class,
        123,
        null,
        'fake_table',
        ['id' => 123, 'foo' => 'bar'],
    );

    expect($version)->toBeInstanceOf(Version::class)
        ->and($version->contents)->toHaveKey('foo', 'bar');
});

it('sets version strategy from string', function (): void {
    config()->set('versionable.version_model', Version::class);

    $service = new VersioningService();

    $version = $service->createVersion(
        FakeVersionedModel::class,
        1,
        null,
        'fake_table',
        ['id' => 1, 'foo' => 'bar'],
        [],
        versionStrategy: 'SNAPSHOT',
    );

    expect($version)->toBeInstanceOf(Version::class);
});

it('sets user id and encrypts selected fields and trims older versions', function (): void {
    config()->set('versionable.version_model', Version::class);

    $service = new VersioningService();

    $version = $service->createVersion(
        FakeVersionedModel::class,
        1,
        null,
        'fake_table',
        ['id' => 1, 'secret' => 'plain', 'other' => 'keep'],
        [],
        userId: 10,
        keepVersionsCount: 1,
        encryptedVersionable: ['secret'],
        versionStrategy: null,
        time: now(),
    );

    expect($version)->toBeInstanceOf(Version::class)
        ->and($version->contents['other'])->toBe('keep')
        ->and($version->contents['secret'])->not->toBe('plain');
});
