<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Support\Facades\Schema;
use Modules\Core\Inspector\SchemaInspector;
use Modules\Core\Overrides\Command;
use Symfony\Component\Console\Command\Command as BaseCommand;

final class InspectorWarmCommand extends Command
{
    protected $signature = 'inspector:warm
                            {tables?* : Optional table names to warm. If none given, all tables on the connection are warmed.}
                            {--connection= : Database connection name.}';

    protected $description = 'Warm the schema inspector cache for the given tables (or all tables) <fg=yellow>(⚡ Modules\Core)</fg=yellow>';

    public function handle(): int
    {
        $connection = $this->option('connection');
        $tables = $this->argument('tables');

        if ($tables === [] || $tables === null) {
            /** @phpstan-ignore staticMethod.notFound */
            $list = Schema::connection($connection)->getTables();
            $tables = array_column($list, 'name');
        }

        if ($tables === []) {
            $this->info('No tables to warm.');

            return BaseCommand::SUCCESS;
        }

        $inspector = SchemaInspector::getInstance();
        $warmed = 0;

        foreach ($tables as $table) {
            $inspected = $inspector->table((string) $table, $connection);
            if ($inspected !== null) {
                $warmed++;
            }
        }

        $this->info(sprintf('Warmed inspector cache for %d table(s).', $warmed));

        return BaseCommand::SUCCESS;
    }
}
