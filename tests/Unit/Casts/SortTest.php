<?php

declare(strict_types=1);

use Modules\Core\Casts\Sort;
use Modules\Core\Casts\SortDirection;

it('creates sort with property and direction enum', function (): void {
    $sort = new Sort('name', SortDirection::DESC);

    expect($sort->property)->toBe('name')
        ->and($sort->direction)->toBe(SortDirection::DESC);
});

it('creates sort with property and direction string', function (): void {
    $sort = new Sort('email', 'asc');

    expect($sort->property)->toBe('email')
        ->and($sort->direction)->toBe(SortDirection::ASC);
});
