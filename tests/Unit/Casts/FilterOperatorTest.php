<?php

declare(strict_types=1);

use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\RequestFilterOperator;

it('maps request operator string to filter operator', function (): void {
    expect(FilterOperator::tryFromRequestOperator('eq'))->toBe(FilterOperator::EQUALS)
        ->and(FilterOperator::tryFromRequestOperator(RequestFilterOperator::LIKE))->toBe(FilterOperator::LIKE);
});

it('returns null for unknown operator', function (): void {
    expect(FilterOperator::tryFromRequestOperator('unknown'))->toBeNull();
});

it('has expected cases', function (): void {
    expect(FilterOperator::EQUALS->value)->toBe('=')
        ->and(FilterOperator::IN->value)->toBe('in');
});
