<?php

declare(strict_types=1);

use Modules\Core\Services\Crud\Aggregate\BucketRollup;

it('adds decimals exactly', function (): void {
    expect(BucketRollup::sum(['0.1', '0.2']))->toBe('0.3')
        ->and(BucketRollup::sum(['0.1000', '0.1000', '0.1000']))->toBe('0.3000')
        ->and(BucketRollup::sum(['3', '4', null]))->toBe('7');
});

it('sum is null when all are null', function (array $values): void {
    expect(BucketRollup::sum($values))->toBeNull();
})->with([
    'nulls' => [[null, null]],
    'no value' => [[]],
]);

it('avg divides by the count of non-null rows', function (): void {
    expect(BucketRollup::avg('70', 4))->toBe('17.5000')
        ->and(BucketRollup::avg('10', 3))->toBe('3.3333')
        ->and(BucketRollup::avg('20', 3))->toBe('6.6667')
        ->and(BucketRollup::avg(null, 0))->toBeNull()
        ->and(BucketRollup::avg('0', 0))->toBeNull();
});

it('min and max ignore nulls', function (): void {
    $values = [null, '10.5', '2.25', null, '9'];

    expect(BucketRollup::min($values))->toBe('2.25')
        ->and(BucketRollup::max($values))->toBe('10.5')
        ->and(BucketRollup::min([null, null]))->toBeNull()
        ->and(BucketRollup::max([]))->toBeNull();
});

it('min and max compare numbers as numbers', function (): void {
    $values = ['9', '10', '-2.5', '1.0E+2'];

    expect(BucketRollup::min($values))->toBe('-2.5')
        ->and(BucketRollup::max($values))->toBe('1.0E+2');
});

it('min and max compare dates as text', function (): void {
    $values = ['2026-01-20 16:00:00', null, '2026-01-05 09:00:00', '2025-12-31 18:00:00'];

    expect(BucketRollup::min($values))->toBe('2025-12-31 18:00:00')
        ->and(BucketRollup::max($values))->toBe('2026-01-20 16:00:00');
});
