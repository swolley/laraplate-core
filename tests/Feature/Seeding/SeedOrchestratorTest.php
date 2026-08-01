<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Modules\Core\Models\Setting;
use Modules\Core\Seeding\SeedLedger;
use Modules\Core\Seeding\SeedNode;
use Modules\Core\Seeding\SeedOrchestrator;
use Modules\Core\Tests\Stubs\Seeding\CommandAwareStubSeeder;
use Modules\Core\Tests\Stubs\Seeding\FailingStubSeeder;
use Modules\Core\Tests\Stubs\Seeding\PassingStubSeeder;

it('stops at the first failure and returns a non-zero exit code', function (): void {
    $orchestrator = app(SeedOrchestrator::class)->withNodes([
        new SeedNode(PassingStubSeeder::class, 'Core'),
        new SeedNode(FailingStubSeeder::class, 'Core', [PassingStubSeeder::class]),
    ]);

    expect($orchestrator->run())->not->toBe(0);
});

it('does not re-execute nodes completed in the interrupted run', function (): void {
    $nodes = [
        new SeedNode(PassingStubSeeder::class, 'Core'),
        new SeedNode(FailingStubSeeder::class, 'Core', [PassingStubSeeder::class]),
    ];

    app(SeedOrchestrator::class)->withNodes($nodes)->run();

    $run_id = app(SeedLedger::class)->lastFailedRunId();
    PassingStubSeeder::$runCount = 0;

    app(SeedOrchestrator::class)->withNodes($nodes)->run($run_id);

    expect(PassingStubSeeder::$runCount)->toBe(0);
});

it('rolls back the failing node without touching earlier ones', function (): void {
    $orchestrator = app(SeedOrchestrator::class)->withNodes([
        new SeedNode(PassingStubSeeder::class, 'Core'),
        new SeedNode(FailingStubSeeder::class, 'Core', [PassingStubSeeder::class]),
    ]);

    $orchestrator->run();

    expect(Setting::query()->withoutGlobalScopes()
        ->where('name', FailingStubSeeder::PARTIAL_MARKER)->exists())->toBeFalse();
});

it('returns 0 and flushes the settings cache once when every node succeeds', function (): void {
    $orchestrator = app(SeedOrchestrator::class)->withNodes([
        new SeedNode(PassingStubSeeder::class, 'Core'),
    ]);

    expect($orchestrator->run())->toBe(0);
});

it('does not run nodes that depend on a failed node', function (): void {
    CommandAwareStubSeeder::$capturedCommand = null;

    $orchestrator = app(SeedOrchestrator::class)->withNodes([
        new SeedNode(PassingStubSeeder::class, 'Core'),
        new SeedNode(FailingStubSeeder::class, 'Core', [PassingStubSeeder::class]),
        new SeedNode(CommandAwareStubSeeder::class, 'Core', [FailingStubSeeder::class]),
    ]);

    $orchestrator->run();

    expect(CommandAwareStubSeeder::$capturedCommand)->toBeNull();
});

it('propagates the invoking command to each resolved node', function (): void {
    CommandAwareStubSeeder::$capturedCommand = null;
    $command = new Command();

    $exit_code = app(SeedOrchestrator::class)
        ->withNodes([new SeedNode(CommandAwareStubSeeder::class, 'Core')])
        ->withCommand($command)
        ->run();

    expect($exit_code)->toBe(0)
        ->and(CommandAwareStubSeeder::$capturedCommand)->toBe($command);
});
