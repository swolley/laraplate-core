<?php

declare(strict_types=1);

use Modules\Core\Models\SeedRun;

/**
 * Exercises the real chain an operator actually invokes — SeedCommand ->
 * DatabaseSeeder -> SeedOrchestrator, resolving the real SeedGraphBuilder
 * graph and a real console command's --resume option — rather than the
 * withNodes() stub seam every other SeedOrchestrator test uses. Kept to one
 * happy path plus the --resume variant: it exists to prove the wiring, not
 * to re-test orchestration logic already covered by SeedOrchestratorTest.
 */
it('runs the production graph end-to-end through db:seed and records it in the ledger', function (): void {
    $this->artisan('db:seed', ['--force' => true])
        ->assertExitCode(0);

    expect(SeedRun::query()->where('status', 'succeeded')->exists())->toBeTrue();
});

it('accepts --resume on a real command instance without throwing', function (): void {
    $this->artisan('db:seed', ['--force' => true])
        ->assertExitCode(0);

    // No failed run exists, so resumeRunId() resolves to null and this runs
    // fresh — the point is that --resume, an option only Modules\Core\Console\
    // SeedCommand declares, is read through DatabaseSeeder::resumeRunId()'s
    // hasOption() guard on a real Command without an InvalidArgumentException.
    $this->artisan('db:seed', ['--resume' => true, '--force' => true])
        ->assertExitCode(0);

    expect(SeedRun::query()->where('status', 'succeeded')->exists())->toBeTrue();
});
