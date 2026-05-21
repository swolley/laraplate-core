<?php

declare(strict_types=1);

use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\RequestFilterOperator;

it('maps request operator string to filter operator', function (): void {
    expect(FilterOperator::tryFromRequestOperator('eq'))->toBe(FilterOperator::Equals)
        ->and(FilterOperator::tryFromRequestOperator(RequestFilterOperator::Like))->toBe(FilterOperator::Like);
});

it('returns null for unknown operator', function (): void {
    expect(FilterOperator::tryFromRequestOperator('unknown'))->toBeNull();
});

it('has expected cases', function (): void {
    expect(FilterOperator::Equals->value)->toBe('=')
        ->and(FilterOperator::In->value)->toBe('in');
});
