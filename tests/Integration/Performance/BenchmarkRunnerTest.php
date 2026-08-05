<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Core\Performance\BenchmarkReport;
use Modules\Core\Performance\BenchmarkRunner;

it('measures only the requested iterations but still runs the warmup', function (): void {
    $runner = app(BenchmarkRunner::class);

    $calls = 0;
    $report = $runner->run(function () use (&$calls): void {
        $calls++;
    }, iterations: 5, warmup: 2);

    expect($report)->toBeInstanceOf(BenchmarkReport::class)
        ->and($report->iterations)->toBe(5)
        ->and($report->warmup)->toBe(2)
        ->and($report->durationStats->count)->toBe(5)
        ->and($calls)->toBe(7);
});

it('counts the database queries executed per iteration', function (): void {
    $runner = app(BenchmarkRunner::class);

    $report = $runner->run(function (): void {
        DB::select('select 1');
        DB::select('select 2');
    }, iterations: 3, warmup: 0);

    expect($report->queryStats->mean)->toBe(2.0)
        ->and($report->queryStats->max)->toBe(2.0);
});

it('reports positive duration and peak memory', function (): void {
    $runner = app(BenchmarkRunner::class);

    $report = $runner->run(static function (): void {
        // trivial work
        $x = 0;
        for ($i = 0; $i < 1000; $i++) {
            $x += $i;
        }
    }, iterations: 4, warmup: 1);

    expect($report->durationStats->min)->toBeGreaterThanOrEqual(0.0)
        ->and($report->durationStats->max)->toBeGreaterThanOrEqual($report->durationStats->min)
        ->and($report->peakMemoryBytes)->toBeGreaterThan(0);
});
