<?php

declare(strict_types=1);

use Modules\Core\Events\ModelRequiresIndexing;
use Modules\Core\Events\ModificationRequiresModeration;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Setting;

/**
 * The listeners of every module write the event of a model under this key and the finalize listener reads it
 * back: a change of one byte strands the events in the cache.
 */
it('keys the indexing event by the table and the key of the model', function (): void {
    $model = new Setting;
    $model->setAttribute('id', 42);

    expect(ModelRequiresIndexing::cacheKey($model))->toBe('model_indexing:' . $model->getTable() . ':42')
        ->and(ModelRequiresIndexing::CACHE_TTL_MINUTES)->toBe(10);
});

it('keys the moderation event by the key of the modification', function (): void {
    $modification = new Modification;
    $modification->setAttribute('id', 7);

    expect(ModificationRequiresModeration::cacheKey($modification))->toBe('modification_moderation:7')
        ->and(ModificationRequiresModeration::CACHE_TTL_MINUTES)->toBe(10);
});
