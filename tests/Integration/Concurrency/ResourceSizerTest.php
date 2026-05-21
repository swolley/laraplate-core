<?php

declare(strict_types=1);

use Modules\Core\Concurrency\Sizing\ResourceSizer;

it('keeps requested parallelism when CPU permits and no DB is queried', function (): void {
    $sizer = ResourceSizer::compute(requestedParallel: 1, batchSize: 100);

    expect($sizer->requestedParallel)->toBe(1);
    expect($sizer->cpuParallel)->toBeGreaterThanOrEqual(1);
    expect($sizer->effectiveParallel)->toBeGreaterThanOrEqual(1);
    expect($sizer->originalBatchSize)->toBe(100);
    expect($sizer->effectiveBatchSize)->toBe(100);
});

it('caps parallelism by CPU cores when requested is too high', function (): void {
    $high = ResourceSizer::detectCpuCores() + 100;

    $sizer = ResourceSizer::compute(requestedParallel: $high, batchSize: 200);

    expect($sizer->requestedParallel)->toBe($high);
    expect($sizer->cpuParallel)->toBeLessThan($high);
    expect($sizer->effectiveParallel)->toBe($sizer->cpuParallel);
    expect($sizer->effectiveBatchSize)->toBeLessThan(200);
    expect($sizer->warnings)->not->toBeEmpty();
});

it('clamps the minimum batch size', function (): void {
    $high = ResourceSizer::detectCpuCores() + 1000;

    $sizer = ResourceSizer::compute(requestedParallel: $high, batchSize: 5);

    expect($sizer->effectiveBatchSize)->toBeGreaterThanOrEqual(10);
});

it('detects at least one CPU core', function (): void {
    expect(ResourceSizer::detectCpuCores())->toBeGreaterThanOrEqual(1);
});
