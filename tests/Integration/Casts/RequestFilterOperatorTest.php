<?php

declare(strict_types=1);

use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\RequestFilterOperator;

it('maps filter operator to request operator', function (): void {
    expect(RequestFilterOperator::tryFromFilterOperator(FilterOperator::Equals))->toBe(RequestFilterOperator::Equals)
        ->and(RequestFilterOperator::tryFromFilterOperator(FilterOperator::Great))->toBe(RequestFilterOperator::Great);
});

it('maps IN filter operator to request operator', function (): void {
    expect(RequestFilterOperator::tryFromFilterOperator(FilterOperator::In))->toBe(RequestFilterOperator::In);
});

it('maps NOT_EQUALS filter operator to request operator', function (): void {
    expect(RequestFilterOperator::tryFromFilterOperator(FilterOperator::NotEquals))->toBe(RequestFilterOperator::NotEquals);
});

it('maps all filter operators to request operators', function (): void {
    foreach (FilterOperator::cases() as $operator) {
        $result = RequestFilterOperator::tryFromFilterOperator($operator);
        expect($result)->toBeInstanceOf(RequestFilterOperator::class);
    }
});

it('has expected cases', function (): void {
    expect(RequestFilterOperator::Equals->value)->toBe('eq')
        ->and(RequestFilterOperator::Great->value)->toBe('gt');
});
