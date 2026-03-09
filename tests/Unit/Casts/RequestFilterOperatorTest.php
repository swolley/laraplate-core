<?php

declare(strict_types=1);

use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\RequestFilterOperator;

it('maps filter operator to request operator', function (): void {
    expect(RequestFilterOperator::tryFromFilterOperator(FilterOperator::EQUALS))->toBe(RequestFilterOperator::EQUALS)
        ->and(RequestFilterOperator::tryFromFilterOperator(FilterOperator::GREAT))->toBe(RequestFilterOperator::GREAT);
});

it('has expected cases', function (): void {
    expect(RequestFilterOperator::EQUALS->value)->toBe('eq')
        ->and(RequestFilterOperator::GREAT->value)->toBe('gt');
});
