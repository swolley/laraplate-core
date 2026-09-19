<?php

declare(strict_types=1);

use Modules\Core\Search\AdaptiveBatchController;

/**
 * Records the chunk sizes a run produced, so the AIMD trajectory is asserted
 * directly. Returns each chunk unchanged, so the concatenated results equal the
 * input in order.
 *
 * @param  array<int, int>  $throwOnCalls  zero-based call indexes that throw
 * @return array{0: Closure, 1: array<int, int>}
 */
function recordingProcess(array &$sizes, array $throwOnCalls = []): Closure
{
    return function (array $chunk) use (&$sizes, $throwOnCalls): array {
        $call_index = count($sizes);
        $sizes[] = count($chunk);

        if (in_array($call_index, $throwOnCalls, true)) {
            throw new RuntimeException('strain');
        }

        return $chunk;
    };
}

it('grows the batch additively on fast successes and preserves order', function (): void {
    $sizes = [];
    $items = range(1, 30);

    $controller = new AdaptiveBatchController(
        minBatch: 2,
        maxBatch: 100,
        targetLatencySeconds: 5.0,
        rampStep: 3,
        clock: static fn (): float => 0.0,
        sleeper: static fn (float $s): null => null,
    );

    $results = $controller->run($items, recordingProcess($sizes));

    expect($sizes)->toBe([2, 5, 8, 11, 4])
        ->and($results)->toBe($items);
});

it('caps the batch at maxBatch', function (): void {
    $sizes = [];

    $controller = new AdaptiveBatchController(
        minBatch: 2,
        maxBatch: 12,
        targetLatencySeconds: 5.0,
        rampStep: 10,
        clock: static fn (): float => 0.0,
        sleeper: static fn (float $s): null => null,
    );

    $controller->run(range(1, 50), recordingProcess($sizes));

    expect(max($sizes))->toBe(12);
});

it('halves the batch and retries fewer items on failure', function (): void {
    $sizes = [];
    $sleeps = [];

    $controller = new AdaptiveBatchController(
        minBatch: 2,
        maxBatch: 100,
        targetLatencySeconds: 5.0,
        rampStep: 3,
        clock: static fn (): float => 0.0,
        sleeper: function (float $s) use (&$sleeps): void {
            $sleeps[] = $s;
        },
    );

    // Call index 1 (the size-5 chunk) throws once; the retry takes fewer items.
    $results = $controller->run(range(1, 12), recordingProcess($sizes, [1]));

    expect($sizes[0])->toBe(2)
        ->and($sizes[1])->toBe(5)   // failed
        ->and($sizes[2])->toBe(2)   // halved retry from the same offset
        ->and($sleeps)->not->toBeEmpty()
        ->and($results)->toBe(range(1, 12));
});

it('shrinks the batch after a slow success', function (): void {
    $sizes = [];
    // Two clock reads per successful batch: [start, end].
    // Batch 1 (size 4) is slow (elapsed 10 > target 5) -> next batch halves.
    $times = [0.0, 0.0,  0.0, 10.0,  0.0, 0.0,  0.0, 0.0];

    $controller = new AdaptiveBatchController(
        minBatch: 2,
        maxBatch: 100,
        targetLatencySeconds: 5.0,
        rampStep: 2,
        clock: function () use (&$times): float {
            return array_shift($times) ?? 0.0;
        },
        sleeper: static fn (float $s): null => null,
    );

    $controller->run(range(1, 10), recordingProcess($sizes));

    expect($sizes)->toBe([2, 4, 2, 2]);
});

it('gives up after repeated failures at the floor', function (): void {
    $sizes = [];

    $controller = new AdaptiveBatchController(
        minBatch: 1,
        maxBatch: 10,
        targetLatencySeconds: 5.0,
        rampStep: 3,
        maxRetriesAtFloor: 2,
        clock: static fn (): float => 0.0,
        sleeper: static fn (float $s): null => null,
    );

    $run = function () use ($controller, &$sizes): array {
        return $controller->run(range(1, 5), recordingProcess($sizes, [0, 1, 2, 3, 4]));
    };

    expect($run)->toThrow(RuntimeException::class);

    // Floor is 1: three failed attempts (1, 2, 3 > maxRetriesAtFloor 2) then it throws.
    expect($sizes)->toBe([1, 1, 1]);
});

it('respects the byte cap even when the count would allow more', function (): void {
    $sizes = [];

    $controller = new AdaptiveBatchController(
        minBatch: 8,
        maxBatch: 100,
        targetLatencySeconds: 5.0,
        rampStep: 4,
        clock: static fn (): float => 0.0,
        sleeper: static fn (float $s): null => null,
    );

    // Each item is 100 bytes, cap 250 -> at most 2 per chunk despite minBatch 8.
    $controller->run(range(1, 10), recordingProcess($sizes), maxBatchBytes: 250, sizeOf: static fn (int $item): int => 100);

    expect(max($sizes))->toBe(2);
});
