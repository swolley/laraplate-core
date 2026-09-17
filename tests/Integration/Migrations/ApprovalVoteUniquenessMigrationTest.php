<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Approval;
use Modules\Core\Models\Disapproval;

/**
 * One actor, one vote per modification. The rule used to arrive in two steps: a
 * migration deduplicating the historical rows, then a second one adding the
 * unique index. The second was folded into the create migrations, which now
 * declare `approvals_actor_vote_uq` and its disapproval twin, so a fresh
 * database enforces the rule from the first migration onwards.
 *
 * The deduplication migration is still here and still runs against databases
 * that predate the rule, which is what the first test covers. The second covers
 * what the folded index buys: the duplicate can no longer be written at all.
 */
function approvalVoteTestConnection(string $name): Illuminate\Database\Connection
{
    config()->set("database.connections.{$name}", [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);
    DB::purge($name);

    return DB::connection($name);
}

/**
 * @return array<string, array{0: string, 1: string}>
 */
function approvalVoteTables(): array
{
    return [
        (new Approval())->getTable() => ['approver_id', 'approver_type'],
        (new Disapproval())->getTable() => ['disapprover_id', 'disapprover_type'],
    ];
}

it('deduplicates historical approval votes, keeping the latest', function (): void {
    $connection = approvalVoteTestConnection('approval-vote-dedup-affinity');
    $schema = $connection->getSchemaBuilder();

    foreach (approvalVoteTables() as $table_name => [$actor_id, $actor_type]) {
        $schema->create($table_name, static function (Blueprint $table) use ($actor_id, $actor_type): void {
            $table->id();
            $table->unsignedBigInteger('modification_id');
            $table->unsignedBigInteger($actor_id);
            $table->string($actor_type);
            $table->text('reason')->nullable();
        });

        $connection->table($table_name)->insert([
            ['id' => 1, 'modification_id' => 10, $actor_id => 20, $actor_type => 'user', 'reason' => 'old'],
            ['id' => 2, 'modification_id' => 10, $actor_id => 20, $actor_type => 'user', 'reason' => 'latest'],
        ]);
    }

    $deduplicate = require module_path('Core', 'database/migrations/2026_08_03_201200_deduplicate_approval_votes.php');

    app('migrator')->usingConnection('approval-vote-dedup-affinity', static function () use ($deduplicate): void {
        $deduplicate->up();

        // Running it twice is what a redeploy does; it must stay a no-op.
        $deduplicate->up();
    });

    foreach (array_keys(approvalVoteTables()) as $table_name) {
        expect($connection->table($table_name)->pluck('reason')->all())->toBe(['latest']);
    }
});

it('refuses a second vote from the same actor once the index is in place', function (): void {
    $connection = approvalVoteTestConnection('approval-vote-unique-affinity');
    $schema = $connection->getSchemaBuilder();

    foreach (approvalVoteTables() as $table_name => [$actor_id, $actor_type]) {
        $schema->create($table_name, static function (Blueprint $table) use ($actor_id, $actor_type, $table_name): void {
            $table->id();
            $table->unsignedBigInteger('modification_id');
            $table->unsignedBigInteger($actor_id);
            $table->string($actor_type);
            $table->text('reason')->nullable();
            $table->unique(['modification_id', $actor_id, $actor_type], "{$table_name}_actor_vote_uq");
        });

        $connection->table($table_name)->insert([
            'modification_id' => 10, $actor_id => 20, $actor_type => 'user', 'reason' => 'first',
        ]);
    }

    foreach (approvalVoteTables() as $table_name => [$actor_id, $actor_type]) {
        expect(fn () => $connection->table($table_name)->insert([
            'modification_id' => 10, $actor_id => 20, $actor_type => 'user', 'reason' => 'duplicate',
        ]))->toThrow(QueryException::class);
    }
});

it('declares the unique index in the create migrations', function (): void {
    foreach ([
        'database/migrations/2024_03_30_161513_create_approvals_table.php' => 'approvals_actor_vote_uq',
        'database/migrations/2024_03_30_161513_create_disapprovals_table.php' => 'disapprovals_actor_vote_uq',
    ] as $migration => $index_name) {
        expect((string) file_get_contents(module_path('Core', $migration)))->toContain($index_name);
    }
});
