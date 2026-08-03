<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Modules\Core\Enums\CoreTables;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Close the rolling-deploy window after the separately recorded cleanup.
        $deduplicate = require __DIR__ . '/2026_08_03_201200_deduplicate_approval_votes.php';
        $deduplicate->up();

        $schema = app('db')->connection()->getSchemaBuilder();

        if (! $schema->hasIndex(CoreTables::Approvals->value, 'approvals_actor_vote_uq')) {
            $schema->table(CoreTables::Approvals->value, static function (Blueprint $table): void {
                $table->unique(
                    ['modification_id', 'approver_id', 'approver_type'],
                    'approvals_actor_vote_uq',
                );
            });
        }

        if (! $schema->hasIndex(CoreTables::Disapprovals->value, 'disapprovals_actor_vote_uq')) {
            $schema->table(CoreTables::Disapprovals->value, static function (Blueprint $table): void {
                $table->unique(
                    ['modification_id', 'disapprover_id', 'disapprover_type'],
                    'disapprovals_actor_vote_uq',
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = app('db')->connection()->getSchemaBuilder();

        if ($schema->hasIndex(CoreTables::Approvals->value, 'approvals_actor_vote_uq')) {
            $schema->table(CoreTables::Approvals->value, static function (Blueprint $table): void {
                $table->dropUnique('approvals_actor_vote_uq');
            });
        }

        if ($schema->hasIndex(CoreTables::Disapprovals->value, 'disapprovals_actor_vote_uq')) {
            $schema->table(CoreTables::Disapprovals->value, static function (Blueprint $table): void {
                $table->dropUnique('disapprovals_actor_vote_uq');
            });
        }
    }
};
