<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Modules\Core\Enums\TimeBucket;

it('has the five buckets of the contract', function (): void {
    expect(array_map(static fn (TimeBucket $bucket): string => $bucket->value, TimeBucket::cases()))
        ->toBe(['day', 'week', 'month', 'quarter', 'year']);
});

it('reads a bucket from its name', function (string $value, TimeBucket $expected): void {
    expect(TimeBucket::fromInput($value))->toBe($expected);
})->with([
    'day' => ['day', TimeBucket::Day],
    'week' => ['week', TimeBucket::Week],
    'month' => ['month', TimeBucket::Month],
    'quarter' => ['quarter', TimeBucket::Quarter],
    'year' => ['year', TimeBucket::Year],
]);

it('reads an unknown bucket as null', function (string $value): void {
    expect(TimeBucket::fromInput($value))->toBeNull();
})->with([
    'a bucket the contract does not have' => ['fortnight'],
    'a plural' => ['months'],
    'nothing' => [''],
]);

it('starts a bucket on its first day, at midnight', function (TimeBucket $bucket, string $day, string $expected): void {
    $start = $bucket->start(CarbonImmutable::parse($day, 'UTC'));

    expect($start->format('Y-m-d H:i:s'))->toBe($expected . ' 00:00:00');
})->with([
    'a day keeps its date and loses its time' => [TimeBucket::Day, '2026-10-20 15:45:10', '2026-10-20'],
    'a week starts on the Monday before a Tuesday' => [TimeBucket::Week, '2026-10-20', '2026-10-19'],
    'a Monday starts its own week' => [TimeBucket::Week, '2026-10-19', '2026-10-19'],
    'a Sunday belongs to the week of the Monday before' => [TimeBucket::Week, '2026-10-25', '2026-10-19'],
    'a week reaches back across a year end' => [TimeBucket::Week, '2026-01-01', '2025-12-29'],
    'a month' => [TimeBucket::Month, '2026-10-20', '2026-10-01'],
    'the last day of a month' => [TimeBucket::Month, '2026-02-28', '2026-02-01'],
    'a quarter in its third month' => [TimeBucket::Quarter, '2026-08-15', '2026-07-01'],
    'the first quarter' => [TimeBucket::Quarter, '2026-03-31', '2026-01-01'],
    'the last quarter' => [TimeBucket::Quarter, '2026-12-31', '2026-10-01'],
    'a year' => [TimeBucket::Year, '2026-10-20', '2026-01-01'],
]);

it('starts a week on the Monday in a locale whose week starts on the Sunday', function (): void {
    $day = CarbonImmutable::parse('2026-10-20', 'UTC')->locale('en_US');

    expect(TimeBucket::Week->start($day)->format('Y-m-d'))->toBe('2026-10-19');
});

it('moves to the start of the next bucket', function (TimeBucket $bucket, string $start, string $expected): void {
    $next = $bucket->next(CarbonImmutable::parse($start, 'UTC'));

    expect($next->format('Y-m-d H:i:s'))->toBe($expected . ' 00:00:00');
})->with([
    'a day' => [TimeBucket::Day, '2026-10-20', '2026-10-21'],
    'a day across a year end' => [TimeBucket::Day, '2026-12-31', '2027-01-01'],
    'a week' => [TimeBucket::Week, '2026-10-19', '2026-10-26'],
    'a week across a year end' => [TimeBucket::Week, '2026-12-28', '2027-01-04'],
    'a month' => [TimeBucket::Month, '2026-01-01', '2026-02-01'],
    'a month across a year end' => [TimeBucket::Month, '2026-12-01', '2027-01-01'],
    'a quarter' => [TimeBucket::Quarter, '2026-04-01', '2026-07-01'],
    'a quarter across a year end' => [TimeBucket::Quarter, '2026-10-01', '2027-01-01'],
    'a year' => [TimeBucket::Year, '2026-01-01', '2027-01-01'],
]);

it('names a bucket by the date of its first day', function (TimeBucket $bucket): void {
    expect($bucket->key(CarbonImmutable::parse('2026-07-01', 'UTC')))->toBe('2026-07-01');
})->with(TimeBucket::cases());
