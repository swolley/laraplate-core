<?php

declare(strict_types=1);

/**
 * Contract checks for {@see \Modules\Core\Helpers\HasVersions} and the overtrue versionable
 * integration. Uses reflection only (no DB): runtime values depend on {@see \Modules\Core\Models\Setting}
 * and cache; {@see false} means versioning is disabled for that table in settings.
 */
use Modules\Core\Tests\Unit\Helpers\VersionableStub;
use Overtrue\LaravelVersionable\VersionStrategy;

it('exposes getVersionStrategy as public so external version creation can call it on the model', function (): void {
    $method = new ReflectionMethod(VersionableStub::class, 'getVersionStrategy');

    expect($method->isPublic())->toBeTrue();
});

it('declares getVersionStrategy as VersionStrategy or false when table versioning is disabled via settings', function (): void {
    $return_type = (new ReflectionMethod(VersionableStub::class, 'getVersionStrategy'))->getReturnType();

    expect($return_type)->not->toBeNull();

    $type_string = $return_type->__toString();
    expect($type_string)->toContain(VersionStrategy::class)
        ->and($type_string)->toContain('false');
});
