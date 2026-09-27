<?php

declare(strict_types=1);

use Modules\Core\Database\Seeders\CoreDatabaseSeeder;

it('seeds the search reranker enabled by default', function (): void {
    $definitions = collect(CoreDatabaseSeeder::runtimeSettingDefinitions())->keyBy('name');

    expect($definitions->get('search.features.reranker')['value'])->toBeTrue()
        ->and($definitions->get('search.reranker.top_k')['value'])->toBe(30);
});
