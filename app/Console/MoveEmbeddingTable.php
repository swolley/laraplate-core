<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use function Laravel\Prompts\confirm;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\ModelEmbedding;
use Override;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

final class MoveEmbeddingTable extends Command
{
    /**
     * The name and signature of the console command.
     */
    #[Override]
    protected $signature = 'model:move-embedding-table';

    /**
     * The console command description.
     */
    #[Override]
    protected $description = 'Move the embedding table to the new connection. <fg=yellow>(⚡ Modules\Core)</fg=yellow>';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $embedding_connection = new ModelEmbedding()->getConnection();
        $configured_connection_name = $embedding_connection->getName();
        $already_migrated = Schema::connection($configured_connection_name)->hasTable('model_embeddings');
        $default_connection = (string) config('database.default');
        $other_connection_exists = $default_connection !== $configured_connection_name && Schema::connection($default_connection)->hasTable('model_embeddings');

        if ($already_migrated) {
            $this->error(sprintf('The embedding table already exists on the %s connection.', $configured_connection_name));

            return SymfonyCommand::SUCCESS;
        }

        // @codeCoverageIgnoreStart
        // Requires database.default to differ from ModelEmbedding's connection (e.g. split DB setup); not exercised in the single-connection sqlite test environment.
        if ($other_connection_exists) {
            if (! confirm('Dropping the current embedding table will lose all the embeddings. Do you want to proceed?', false)) {
                return SymfonyCommand::SUCCESS;
            }

            $this->info(sprintf('Dropping the embedding table on %s connection...', $default_connection));
            Schema::connection($default_connection)->dropIfExists('model_embeddings');
        }
        // @codeCoverageIgnoreEnd

        $migration_source = file_get_contents($this->modelEmbeddingsMigrationFile());
        $migration_source = preg_replace('/^\s*<\?php\s*/', '', $migration_source);

        /** @var \Illuminate\Database\Migrations\Migration $migration */
        $migration = eval($migration_source);
        $migration->up();

        $this->info(sprintf('Embedding table migrated to %s connection.', $configured_connection_name));

        return SymfonyCommand::SUCCESS;
    }

    private function modelEmbeddingsMigrationFile(): string
    {
        $relative = 'database/migrations/2024_11_05_233754_create_model_embeddings_table.php';
        $via_module = module_path('Core', $relative);

        if (is_file($via_module)) {
            return $via_module;
        }

        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative); // @codeCoverageIgnore
    }
}
