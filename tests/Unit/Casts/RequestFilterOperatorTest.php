<?php

declare(strict_types=1);

use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\RequestFilterOperator;

it('maps filter operator to request operator', function (): void {
    expect(RequestFilterOperator::tryFromFilterOperator(FilterOperator::EQUALS))->toBe(RequestFilterOperator::EQUALS)
        ->and(RequestFilterOperator::tryFromFilterOperator(FilterOperator::GREAT))->toBe(RequestFilterOperator::GREAT);
});

it('maps IN filter operator to request operator', function (): void {
    expect(RequestFilterOperator::tryFromFilterOperator(FilterOperator::IN))->toBe(RequestFilterOperator::IN);
});

it('maps NOT_EQUALS filter operator to request operator', function (): void {
    expect(RequestFilterOperator::tryFromFilterOperator(FilterOperator::NOT_EQUALS))->toBe(RequestFilterOperator::NOT_EQUALS);
});

it('maps all filter operators to request operators', function (): void {
    foreach (FilterOperator::cases() as $operator) {
        $result = RequestFilterOperator::tryFromFilterOperator($operator);
        expect($result)->toBeInstanceOf(RequestFilterOperator::class);
    }
});

it('has expected cases', function (): void {
    expect(RequestFilterOperator::EQUALS->value)->toBe('eq')
        ->and(RequestFilterOperator::GREAT->value)->toBe('gt');
});
