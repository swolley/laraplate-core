<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use function Laravel\Prompts\confirm;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\ModelEmbedding;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

final class MoveEmbeddingTable extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'model:move-embedding-table';

    /**
     * The console command description.
     */
    protected $description = 'Move the embedding table to the new connection. <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $configured_connection = new ModelEmbedding()->getConnection();
        // $configured_connection = config('search.embedding_model_connection');
        $already_migrated = DB::connection($configured_connection)->table('model_embeddings')->exists();
        $default_connection = config('database.default');
        $other_connection_exists = $default_connection !== $configured_connection && DB::connection($default_connection)->table('model_embeddings')->exists();

        if ($already_migrated) {
            $this->error("The embedding table already exists on the {$configured_connection} connection.");

            return SymfonyCommand::SUCCESS;
        }

        if ($other_connection_exists) {
            if (! confirm('Dropping the current embedding table will lose all the embeddings. Do you want to proceed?', false)) {
                return SymfonyCommand::SUCCESS;
            }

            $this->info("Dropping the embedding table on {$default_connection} connection...");
            Schema::connection($default_connection)->dropIfExists('model_embeddings');
        }

        $code = file_get_contents(module_path('Core', 'database/migrations/2024_11_05_233754_create_model_embeddings_table.php'));
        $migration_class = eval($code);
        $migration_class::up();

        $this->info("Embedding table migrated to {$configured_connection} connection.");

        return SymfonyCommand::SUCCESS;
    }
}
