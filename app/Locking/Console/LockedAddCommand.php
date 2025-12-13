<?php

declare(strict_types=1);

namespace Modules\Core\Locking\Console;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Modules\Core\Overrides\Command;
use ReflectionClass;
use Symfony\Component\Console\Command\Command as BaseCommand;

class LockedAddCommand extends Command
{
    public $signature = 'lock:locked-add {model} {--namespace=}';

    public $description = 'Add a migration to add locked columns to a model <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    protected $operation = 'add';

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $model = $this->argument('model');

        // $namespace = config('locked.default_namespace');
        if (Str::contains('\\', $model)) {
            $namespace = Str::replaceMatches('\\\w+$', '', $model);
        } else {
            $namespace = 'App\\Models';
        }

        if ($this->option('namespace')) {
            $namespace = $this->option('namespace');
        }

        $className = $namespace . '\\' . $model;

        if (! class_exists($className)) {
            $this->error(sprintf('Model %s does not exist', $className));

            return BaseCommand::INVALID;
        }

        $instance = new ReflectionClass($className)->newInstanceWithoutConstructor();
        $table = $instance->getTable();
        $fileContents = $this->getStubContents($this->getStubPath(), [
            'ModelTable' => $table,
        ]);

        $filePath = now()->format('Y_m_d_His') . $this->generateMigrationPath($table);
        $path = App::databasePath('migrations/' . $filePath);

        if (! $this->files->exists($path)) {
            $this->files->put($path, $fileContents);
        } else {
            $this->info(sprintf('File : %s already exists', $path));
        }

        return BaseCommand::SUCCESS;
    }

    public function generateMigrationPath(Model $instance): string
    {
        return sprintf('_%s_locked_columns_to_%s.php', $this->operation, $instance->getTable());
    }

    /**
     * Replace the stub variables(key) with the desire value.
     *
     * @param  array  $stubVariables
     */
    public function getStubContents($stub, $stubVariables = []): mixed
    {
        $contents = file_get_contents($stub);

        foreach ($stubVariables as $search => $replace) {
            $contents = str_replace('$' . $search . '$', $replace, $contents);
        }

        return $contents;
    }

    /**
     * Return the stub file path.
     */
    public function getStubPath(): string
    {
        return module_path('Core', sprintf('Locking/Stubs/%s_locked_column_to_table.php.stub', $this->operation));
    }
}
