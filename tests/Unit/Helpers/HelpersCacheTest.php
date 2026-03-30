<?php

declare(strict_types=1);

use Modules\Core\Helpers\HelpersCache;
use Modules\Core\Models\User;

it('stores and retrieves model class lists', function (): void {
    HelpersCache::clearAll();

    expect(HelpersCache::getModels('active'))->toBeNull();

    HelpersCache::setModels('active', [User::class]);

    expect(HelpersCache::getModels('active'))->toBe([User::class]);
});

it('stores and retrieves connection driver lists', function (): void {
    HelpersCache::clearAll();

    HelpersCache::setConnections('active', ['sqlite']);

    expect(HelpersCache::getConnections('active'))->toBe(['sqlite']);
});

it('clearModels only clears models cache', function (): void {
    HelpersCache::clearAll();
    HelpersCache::setModels('active', [User::class]);
    HelpersCache::setConnections('active', ['sqlite']);

    HelpersCache::clearModels();

    expect(HelpersCache::getModels('active'))->toBeNull()
        ->and(HelpersCache::getConnections('active'))->toBe(['sqlite']);
});

it('clearConnections only clears connections cache', function (): void {
    HelpersCache::clearAll();
    HelpersCache::setModels('active', [User::class]);
    HelpersCache::setConnections('active', ['sqlite']);

    HelpersCache::clearConnections();

    expect(HelpersCache::getConnections('active'))->toBeNull()
        ->and(HelpersCache::getModels('active'))->toBe([User::class]);
});

it('clearAll removes both caches', function (): void {
    HelpersCache::setModels('active', [User::class]);
    HelpersCache::setConnections('active', ['sqlite']);

    HelpersCache::clearAll();

    expect(HelpersCache::getModels('active'))->toBeNull()
        ->and(HelpersCache::getConnections('active'))->toBeNull();
});
