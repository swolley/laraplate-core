<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('profiles the given endpoint and reports latency columns', function (): void {
    Route::get('/perf-cmd-probe', fn () => response()->json(['ok' => true]));

    $this->artisan('perf:bench', [
        'endpoint' => ['GET:/perf-cmd-probe'],
        '--iterations' => 2,
        '--warmup' => 0,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('/perf-cmd-probe')
        ->expectsOutputToContain('p95');
});

it('fails clearly on a malformed endpoint spec', function (): void {
    $this->artisan('perf:bench', [
        'endpoint' => ['not-a-valid-spec'],
    ])->assertFailed();
});
