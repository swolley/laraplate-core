<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Approval;
use Modules\Core\Models\Disapproval;

it('deduplicates historical approval votes before enforcing one vote per actor', function (): void {
    $connection_name = 'approval-vote-migration-affinity';
    config()->set("database.connections.{$connection_name}", [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);
    DB::purge($connection_name);

    $connection = DB::connection($connection_name);
    $schema = $connection->getSchemaBuilder();
    $approval_table = (new Approval())->getTable();
    $disapproval_table = (new Disapproval())->getTable();

    foreach ([
        $approval_table => ['approver_id', 'approver_type'],
        $disapproval_table => ['disapprover_id', 'disapprover_type'],
    ] as $table_name => [$actor_id, $actor_type]) {
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
    $add_constraints = require module_path('Core', 'database/migrations/2026_08_03_201206_add_unique_actor_constraints_to_approval_votes.php');

    app('migrator')->usingConnection($connection_name, static function () use (
        $add_constraints,
        $approval_table,
        $connection,
        $deduplicate,
        $disapproval_table,
        $schema,
    ): void {
        $deduplicate->up();

        $connection->table($approval_table)->insert([
            'id' => 3,
            'modification_id' => 10,
            'approver_id' => 20,
            'approver_type' => 'user',
            'reason' => 'approval inserted during deploy',
        ]);
        $connection->table($disapproval_table)->insert([
            'id' => 3,
            'modification_id' => 10,
            'disapprover_id' => 20,
            'disapprover_type' => 'user',
            'reason' => 'disapproval inserted during deploy',
        ]);

        $deduplicate->up();
        $schema->table($approval_table, static function (Blueprint $table): void {
            $table->unique(
                ['modification_id', 'approver_id', 'approver_type'],
                'approvals_actor_vote_uq',
            );
        });
        $connection->table($disapproval_table)->insert([
            'id' => 4,
            'modification_id' => 10,
            'disapprover_id' => 20,
            'disapprover_type' => 'user',
            'reason' => 'disapproval inserted after partial DDL',
        ]);

        $add_constraints->up();

        expect($connection->table($approval_table)->pluck('reason')->all())->toBe(['approval inserted during deploy'])
            ->and($connection->table($disapproval_table)->pluck('reason')->all())->toBe(['disapproval inserted after partial DDL'])
            ->and(fn () => $connection->table($approval_table)->insert([
                'modification_id' => 10,
                'approver_id' => 20,
                'approver_type' => 'user',
                'reason' => 'duplicate',
            ]))->toThrow(QueryException::class)
            ->and(fn () => $connection->table($disapproval_table)->insert([
                'modification_id' => 10,
                'disapprover_id' => 20,
                'disapprover_type' => 'user',
                'reason' => 'duplicate',
            ]))->toThrow(QueryException::class);

        $add_constraints->down();

        expect(fn () => $connection->table($approval_table)->insert([
            'modification_id' => 10,
            'approver_id' => 20,
            'approver_type' => 'user',
            'reason' => 'allowed after rollback',
        ]))->not->toThrow(QueryException::class);
    });
});
