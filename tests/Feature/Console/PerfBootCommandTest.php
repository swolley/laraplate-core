<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Modules\Core\Contracts\BootSampler;

it('child mode prints a parseable boot time', function (): void {
    Artisan::call('perf:boot', ['--child' => true]);

    expect(Artisan::output())->toMatch('/BOOT_MS=[\d.]+/');
});

it('aggregates boot samples into percentiles', function (): void {
    app()->instance(BootSampler::class, new class implements BootSampler
    {
        public function sample(int $runs): array
        {
            return [10.0, 20.0, 30.0];
        }
    });

    $exit = Artisan::call('perf:boot', ['--runs' => 3]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('p95')
        // nearest-rank p95 of [10,20,30] is 30.0
        ->and($output)->toContain('30.0');
});

it('fails when no boot samples could be collected', function (): void {
    app()->instance(BootSampler::class, new class implements BootSampler
    {
        public function sample(int $runs): array
        {
            return [];
        }
    });

    $exit = Artisan::call('perf:boot', ['--runs' => 3]);

    expect($exit)->toBe(1);
});
