<?php

declare(strict_types=1);

use Modules\Core\Casts\SortDirection;

it('has asc and desc cases', function (): void {
    expect(SortDirection::ASC->value)->toBe('asc')
        ->and(SortDirection::DESC->value)->toBe('desc');
});

it('tryFrom accepts lowercase', function (): void {
    expect(SortDirection::tryFrom('asc'))->toBe(SortDirection::ASC)
        ->and(SortDirection::tryFrom('desc'))->toBe(SortDirection::DESC);
});
