<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\Setting;
use Modules\Core\Seeding\SeedLedger;
use Modules\Core\Seeding\SeedNode;
use Modules\Core\Seeding\SeedOrchestrator;
use Modules\Core\Services\SettingsCacheCoordinator;
use Modules\Core\Tests\Stubs\Seeding\CommandAwareStubSeeder;
use Modules\Core\Tests\Stubs\Seeding\FailingStubSeeder;
use Modules\Core\Tests\Stubs\Seeding\PassingStubSeeder;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

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
    $flush_count = 0;
    app(SettingsCacheCoordinator::class)->registerInvalidator(function () use (&$flush_count): void {
        $flush_count++;
    });

    $orchestrator = app(SeedOrchestrator::class)->withNodes([
        new SeedNode(PassingStubSeeder::class, 'Core'),
    ]);

    expect($orchestrator->run())->toBe(0)
        ->and($flush_count)->toBe(1);
});

it('makes db:seed fail loudly, not exit 0, when a node fails', function (): void {
    // Bind a pre-configured orchestrator so DatabaseSeeder's real
    // app(SeedOrchestrator::class) resolution (used by the actual db:seed
    // command) picks up a failing node, exercising the command-level
    // failure path rather than SeedOrchestrator::run() called directly.
    $orchestrator = app(SeedOrchestrator::class)->withNodes([
        new SeedNode(FailingStubSeeder::class, 'Core'),
    ]);

    app()->instance(SeedOrchestrator::class, $orchestrator);

    // $this->artisan() drives the command through Kernel::call(), which —
    // unlike the real `artisan` CLI entry point (Kernel::handle()) — does not
    // catch the command's exception into an exit code; it propagates the
    // exception straight to the caller. That exception (DatabaseSeeder
    // throws RuntimeException on a non-zero orchestrator exit code) is
    // exactly what the real CLI entry point catches and turns into a
    // non-zero process exit, so asserting it is thrown here is the
    // equivalent proof that `db:seed` does not exit 0 on a failing node.
    expect(fn () => $this->artisan('db:seed', ['--force' => true])->run())
        ->toThrow(RuntimeException::class, 'Seeding failed; see the run ledger for the failing node.');
});

it('announces the resumed run id and skipped node count when resuming', function (): void {
    $nodes = [
        new SeedNode(PassingStubSeeder::class, 'Core'),
        new SeedNode(FailingStubSeeder::class, 'Core', [PassingStubSeeder::class]),
    ];

    app(SeedOrchestrator::class)->withNodes($nodes)->run();

    $run_id = app(SeedLedger::class)->lastFailedRunId();
    expect($run_id)->not->toBeNull();

    $output = new BufferedOutput();
    $command = new Command();
    $command->setOutput(new OutputStyle(new ArrayInput([]), $output));

    app(SeedOrchestrator::class)->withNodes($nodes)->withCommand($command)->run($run_id);

    expect($output->fetch())
        ->toContain((string) $run_id)
        ->toContain('skipping 1 already-completed node');
});

it('does not run any node after a failure, regardless of declared dependencies', function (): void {
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

it('includes the rollback-scope caveat in both the operator-facing message and the log context', function (): void {
    Log::spy();

    $output = new BufferedOutput();
    $command = new Command();
    $command->setOutput(new OutputStyle(new ArrayInput([]), $output));

    app(SeedOrchestrator::class)
        ->withNodes([new SeedNode(FailingStubSeeder::class, 'Core')])
        ->withCommand($command)
        ->run();

    expect($output->fetch())->toContain('Rollback only covers writes on the default database connection');

    Log::shouldHaveReceived('error')->once()->withArgs(
        fn (string $message, array $context): bool => isset($context['rollback_caveat'])
            && str_contains($context['rollback_caveat'], 'Rollback only covers writes on the default database connection'),
    );
});
