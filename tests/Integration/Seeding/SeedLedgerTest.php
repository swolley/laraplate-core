<?php

declare(strict_types=1);

use Modules\Core\Models\SeedRun;
use Modules\Core\Seeding\SeedLedger;

it('records a successful node with its content hash', function (): void {
    $ledger = app(SeedLedger::class);
    $ledger->start('run-1', 'CoreSeeder');
    $ledger->succeed('run-1', 'CoreSeeder', 'abc123');

    $row = SeedRun::query()->where('run_id', 'run-1')->sole();

    expect($row->status)->toBe('succeeded')
        ->and($row->content_hash)->toBe('abc123')
        ->and($row->finished_at)->not->toBeNull();
});

it('records a failure with the error message', function (): void {
    $ledger = app(SeedLedger::class);
    $ledger->start('run-2', 'ErpSeeder');
    $ledger->fail('run-2', 'ErpSeeder', 'constraint violation');

    $row = SeedRun::query()->where('run_id', 'run-2')->sole();

    expect($row->status)->toBe('failed')
        ->and($row->error)->toContain('constraint violation');
});

it('lists only the completed nodes of a run', function (): void {
    $ledger = app(SeedLedger::class);
    $ledger->start('run-3', 'A');
    $ledger->succeed('run-3', 'A', 'h1');
    $ledger->start('run-3', 'B');
    $ledger->fail('run-3', 'B', 'boom');

    expect($ledger->completedNodes('run-3'))->toBe(['A']);
});

it('finds the most recent failed run', function (): void {
    $ledger = app(SeedLedger::class);
    $ledger->start('run-4', 'A');
    $ledger->fail('run-4', 'A', 'boom');

    expect($ledger->lastFailedRunId())->toBe('run-4');
});

it('overwrites a previously failed node back to running when the run is resumed', function (): void {
    $ledger = app(SeedLedger::class);
    $ledger->start('run-5', 'A');
    $ledger->fail('run-5', 'A', 'boom');

    $ledger->start('run-5', 'A');

    $rows = SeedRun::query()->where('run_id', 'run-5')->where('node', 'A')->get();
    $row = $rows->sole();

    expect($row->status)->toBe('running')
        ->and($row->error)->toBeNull()
        ->and($row->finished_at)->toBeNull();
});

it('picks the newest failed run rather than an arbitrary one', function (): void {
    $ledger = app(SeedLedger::class);
    $ledger->start('run-A', 'X');
    $ledger->fail('run-A', 'X', 'boom');
    $ledger->start('run-B', 'X');
    $ledger->fail('run-B', 'X', 'boom');

    expect($ledger->lastFailedRunId())->toBe('run-B');
});

it('stops offering a failed run to resume once a newer run has succeeded', function (): void {
    // A failure that was never resumed must not stay the answer forever: a
    // deploy script that habitually passes --resume would otherwise skip
    // every node that already succeeded in a later run while exiting 0.
    $ledger = app(SeedLedger::class);
    $ledger->start('run-old', 'X');
    $ledger->fail('run-old', 'X', 'boom');

    $ledger->start('run-new', 'X');
    $ledger->succeed('run-new', 'X', 'hash');

    expect($ledger->lastFailedRunId())->toBeNull();
});
