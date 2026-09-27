<?php

declare(strict_types=1);

use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Services\PerModelSettingResolver;

it('builds the same name whether or not the prefix carries a trailing separator', function (string $prefix): void {
    expect(PerModelSettingResolver::nameFor($prefix, 'cms_contents'))
        ->toBe(PerModelSettingResolver::nameFor(mb_rtrim($prefix, '_.'), 'cms_contents'));
})->with([
    'soft_deletes.enabled.',
    'versioning.strategy_',
    'locking.enabled.',
    'locking.optimistic_',
    'translations.locale_fallback.',
    'translations.auto_',
    'notifications.threshold.',
]);

it('separates capability and table with exactly one dot', function (): void {
    expect(PerModelSettingResolver::nameFor('versioning.strategy', 'cms_contents'))
        ->toBe('versioning.strategy.cms_contents')
        ->and(PerModelSettingResolver::nameFor('versioning.strategy_', 'cms_contents'))
        ->toBe('versioning.strategy.cms_contents')
        ->and(PerModelSettingResolver::nameFor('versioning.strategy_.', 'cms_contents'))
        ->toBe('versioning.strategy.cms_contents');
});

/**
 * Per-model settings sit next to the general settings of their domain, so the
 * name starts with that domain: sorting the settings keeps related rows together.
 */
it('names every per-model setting after its domain', function (string $constant, string $expected): void {
    expect(PerModelSettingResolver::nameFor($constant, 'cms_contents'))->toBe($expected);
})->with([
    [CoreDatabaseSeeder::SOFT_DELETES_NAME_PREFIX, 'soft_deletes.enabled.cms_contents'],
    [CoreDatabaseSeeder::VERSIONING_NAME_PREFIX, 'versioning.strategy.cms_contents'],
    [CoreDatabaseSeeder::LOCK_NAME_PREFIX, 'locking.enabled.cms_contents'],
    [CoreDatabaseSeeder::OPTIMISTIC_LOCK_NAME_PREFIX, 'locking.optimistic.cms_contents'],
    [CoreDatabaseSeeder::TRANSLATION_FALLBACK_NAME_PREFIX, 'translations.locale_fallback.cms_contents'],
    [CoreDatabaseSeeder::AUTO_TRANSLATE_NAME_PREFIX, 'translations.auto.cms_contents'],
    [CoreDatabaseSeeder::APPROVAL_THRESHOLD_NAME_PREFIX, 'notifications.threshold.cms_contents'],
]);
