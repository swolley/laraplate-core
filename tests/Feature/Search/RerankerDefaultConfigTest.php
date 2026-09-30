<?php

declare(strict_types=1);

use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;

it('seeds the search reranker enabled by default', function (): void {
    $definitions = collect(CoreDatabaseSeeder::runtimeSettingDefinitions())->keyBy('name');

    expect($definitions->get('search.reranker.enabled')['value'])->toBeTrue()
        ->and($definitions->get('search.reranker.top_k')['value'])->toBe(30);
});

it('seeds the reranker blend weight at the historical 0.6', function (): void {
    $definition = collect(CoreDatabaseSeeder::runtimeSettingDefinitions())->keyBy('name')->get('search.reranker.weight');

    expect($definition)->not->toBeNull()
        ->and($definition['value'])->toBe(0.6)
        ->and($definition['type'])->toBe(SettingTypeEnum::Float)
        ->and($definition['group_name'])->toBe('search');
});
