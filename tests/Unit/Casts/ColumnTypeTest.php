<?php

declare(strict_types=1);

use Modules\Core\Casts\ColumnType;

it('identifies aggregate columns', function (): void {
    expect(ColumnType::COUNT->isAggregateColumn())->toBeTrue()
        ->and(ColumnType::SUM->isAggregateColumn())->toBeTrue()
        ->and(ColumnType::AVG->isAggregateColumn())->toBeTrue()
        ->and(ColumnType::MIN->isAggregateColumn())->toBeTrue()
        ->and(ColumnType::MAX->isAggregateColumn())->toBeTrue();
});

it('identifies non aggregate columns', function (): void {
    expect(ColumnType::COLUMN->isAggregateColumn())->toBeFalse()
        ->and(ColumnType::APPEND->isAggregateColumn())->toBeFalse()
        ->and(ColumnType::METHOD->isAggregateColumn())->toBeFalse();
});
