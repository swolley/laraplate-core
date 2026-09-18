<?php

declare(strict_types=1);

use Modules\Core\Services\ElasticsearchService;

it('accepts a major version within the supported range', function (): void {
    expect(ElasticsearchService::majorIsSupported(8, 8, 8))->toBeTrue()
        ->and(ElasticsearchService::majorIsSupported(8, 8, 9))->toBeTrue()
        ->and(ElasticsearchService::majorIsSupported(9, 8, 9))->toBeTrue();
});

it('rejects a major version outside the supported range', function (): void {
    expect(ElasticsearchService::majorIsSupported(2, 8, 8))->toBeFalse()
        ->and(ElasticsearchService::majorIsSupported(9, 8, 8))->toBeFalse()
        ->and(ElasticsearchService::majorIsSupported(7, 8, 9))->toBeFalse();
});
