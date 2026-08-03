<?php

declare(strict_types=1);

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Modules\Core\Enums\CoreTables;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /** @var ConnectionInterface $connection */
        $connection = app('db')->connection();

        $vote_tables = [
            [CoreTables::Approvals->value, 'approver_id', 'approver_type'],
            [CoreTables::Disapprovals->value, 'disapprover_id', 'disapprover_type'],
        ];

        foreach ($vote_tables as [$table_name, $actor_id_column, $actor_type_column]) {
            $duplicates = $connection->table($table_name)
                ->select(['modification_id', $actor_id_column, $actor_type_column])
                ->selectRaw('MAX(id) AS keep_id')
                ->groupBy(['modification_id', $actor_id_column, $actor_type_column])
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicates as $duplicate) {
                $connection->table($table_name)
                    ->where('modification_id', $duplicate->modification_id)
                    ->where($actor_id_column, $duplicate->{$actor_id_column})
                    ->where($actor_type_column, $duplicate->{$actor_type_column})
                    ->where('id', '<>', $duplicate->keep_id)
                    ->delete();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Historical duplicate votes cannot be restored safely.
    }
};
