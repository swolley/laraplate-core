<?php

declare(strict_types=1);

use Modules\Core\Casts\ListRequestData;

/**
 * Invoke the protected pagination resolver on a constructor-less instance and read
 * back the resulting window.
 *
 * @param  array<string, mixed>  $validated
 * @return array{page: ?int, from: ?int, to: ?int, take: ?int, pagination: ?int, skip: ?int}
 */
function extract_pagination_window(array $validated): array
{
    $data = (new ReflectionClass(ListRequestData::class))->newInstanceWithoutConstructor();

    $method = new ReflectionMethod($data, 'extractPagination');
    $method->setAccessible(true);
    $method->invoke($data, $validated);

    $read = static function (string $prop) use ($data): mixed {
        $p = new ReflectionProperty($data, $prop);
        $p->setAccessible(true);

        return $p->isInitialized($data) ? $p->getValue($data) : null;
    };

    return [
        'page' => $read('page'),
        'from' => $read('from'),
        'to' => $read('to'),
        'take' => $read('take'),
        'pagination' => $read('pagination'),
        'skip' => $read('skip'),
    ];
}

it('populates a consistent from/to window for a plain list request', function (): void {
    $window = extract_pagination_window([]);

    // listByPagination slices with take(to - from + 1); it must equal the page size
    // and never collapse to a single row.
    expect($window['page'])->toBe(1)
        ->and($window['from'])->toBe(1)
        ->and($window['to'])->toBe($window['pagination'])
        ->and($window['to'] - $window['from'] + 1)->toBe($window['pagination'])
        ->and($window['pagination'])->toBeGreaterThan(1);
});

it('produces non-overlapping windows of exactly the page size for page pagination', function (): void {
    $page1 = extract_pagination_window(['page' => 1, 'pagination' => 10]);
    $page2 = extract_pagination_window(['page' => 2, 'pagination' => 10]);

    // Each page slices exactly `pagination` rows: take(to - from + 1) == pagination.
    expect($page1['from'])->toBe(1)
        ->and($page1['to'])->toBe(10)
        ->and($page1['to'] - $page1['from'] + 1)->toBe(10)
        // The next page starts right after the previous one — no overlap.
        ->and($page2['from'])->toBe(11)
        ->and($page2['to'])->toBe(20)
        ->and($page2['from'])->toBe($page1['to'] + 1);
});
