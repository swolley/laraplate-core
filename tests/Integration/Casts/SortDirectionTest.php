<?php

declare(strict_types=1);

use Modules\Core\Casts\SortDirection;

it('has asc and desc cases', function (): void {
    expect(SortDirection::Asc->value)->toBe('asc')
        ->and(SortDirection::Desc->value)->toBe('desc');
});

it('tryFrom accepts lowercase', function (): void {
    expect(SortDirection::tryFrom('asc'))->toBe(SortDirection::Asc)
        ->and(SortDirection::tryFrom('desc'))->toBe(SortDirection::Desc);
});
