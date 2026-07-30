<?php

declare(strict_types=1);

use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Services\PerModelSettingResolver;

it('builds the same name whether or not the prefix carries a trailing separator', function (string $prefix): void {
    expect(PerModelSettingResolver::nameFor($prefix, 'cms_contents'))
        ->toBe(PerModelSettingResolver::nameFor(rtrim($prefix, '_.'), 'cms_contents'));
})->with([
    'soft_deletes_',
    'version_strategy_',
    'lock_',
    'optimistic_lock_',
    'translation_fallback_',
    'auto_translate_',
    'ai_moderation_',
]);

it('separates prefix and table with exactly one underscore', function (): void {
    expect(PerModelSettingResolver::nameFor('version_strategy_', 'cms_contents'))
        ->toBe('version_strategy_cms_contents')
        ->and(PerModelSettingResolver::nameFor('version_strategy', 'cms_contents'))
        ->toBe('version_strategy_cms_contents')
        // Commands used to append the table with a dot, producing a third spelling.
        ->and(PerModelSettingResolver::nameFor('version_strategy_.', 'cms_contents'))
        ->toBe('version_strategy_cms_contents');
});

/**
 * The seeder writes these settings from its prefix constants while the model
 * traits read them from string literals. When the two drifted apart, every
 * per-model setting silently fell back to its hardcoded default.
 */
it('writes the settings under the very name the readers look up', function (string $constant, string $literal): void {
    expect(PerModelSettingResolver::nameFor($constant, 'cms_contents'))
        ->toBe(PerModelSettingResolver::nameFor($literal, 'cms_contents'));
})->with([
    [CoreDatabaseSeeder::SOFT_DELETES_NAME_PREFIX, 'soft_deletes'],
    [CoreDatabaseSeeder::VERSIONING_NAME_PREFIX, 'version_strategy'],
    [CoreDatabaseSeeder::LOCK_NAME_PREFIX, 'lock'],
    [CoreDatabaseSeeder::OPTIMISTIC_LOCK_NAME_PREFIX, 'optimistic_lock'],
    [CoreDatabaseSeeder::TRANSLATION_FALLBACK_NAME_PREFIX, 'translation_fallback'],
    [CoreDatabaseSeeder::AUTO_TRANSLATE_NAME_PREFIX, 'auto_translate'],
    [CoreDatabaseSeeder::AI_MODERATION_NAME_PREFIX, 'ai_moderation'],
]);
