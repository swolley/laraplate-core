<?php

declare(strict_types=1);

use Modules\Core\Casts\ColumnType;

it('identifies aggregate columns', function (): void {
    expect(ColumnType::Count->isAggregateColumn())->toBeTrue()
        ->and(ColumnType::Sum->isAggregateColumn())->toBeTrue()
        ->and(ColumnType::Avg->isAggregateColumn())->toBeTrue()
        ->and(ColumnType::Min->isAggregateColumn())->toBeTrue()
        ->and(ColumnType::Max->isAggregateColumn())->toBeTrue();
});

it('identifies non aggregate columns', function (): void {
    expect(ColumnType::Column->isAggregateColumn())->toBeFalse()
        ->and(ColumnType::Append->isAggregateColumn())->toBeFalse()
        ->and(ColumnType::Method->isAggregateColumn())->toBeFalse();
});
