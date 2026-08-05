<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Core\Performance\EndpointBenchmarkReport;
use Modules\Core\Performance\EndpointProfiler;

it('profiles an http endpoint through the real kernel', function (): void {
    Route::get('/perf-probe', function () {
        DB::select('select 1');

        return response()->json(['ok' => true]);
    });

    $profiler = app(EndpointProfiler::class);

    $report = $profiler->profile('GET', '/perf-probe', iterations: 3, warmup: 1);

    expect($report)->toBeInstanceOf(EndpointBenchmarkReport::class)
        ->and($report->method)->toBe('GET')
        ->and($report->uri)->toBe('/perf-probe')
        ->and($report->benchmark->durationStats->count)->toBe(3)
        // the route body ran (its `select 1` was observed), proving the kernel dispatched it
        ->and($report->benchmark->queryStats->max)->toBeGreaterThanOrEqual(1.0);
});

it('exposes the last response status so failures are visible', function (): void {
    Route::get('/perf-probe-ok', fn () => response('pong', 200));

    $profiler = app(EndpointProfiler::class);

    $report = $profiler->profile('GET', '/perf-probe-ok', iterations: 2, warmup: 0);

    expect($report->lastStatus)->toBe(200);
});
