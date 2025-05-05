<?php

declare(strict_types=1);

namespace Modules\Core\Locking\Console;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\App;
use Modules\Core\Overrides\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Console\Command\Command as BaseCommand;

class LockedAddCommand extends Command
{
    public $signature = 'lock:locked-add {model} {--namespace=}';

    public $description = 'Add a migration to add locked columns to a model <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    protected $operation = 'add';

    public function __construct(protected \Illuminate\Filesystem\Filesystem $files)
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
            $this->error("Model {$className} does not exist");

            return BaseCommand::FAILURE;
        }

        $instance = new $className();
        $fileContents = $this->getStubContents($this->getStubPath(), [
            'ModelTable' => $instance->getTable(),
        ]);

        $filePath = now()->format('Y_m_d_His') . $this->generateMigrationPath($instance->getTable());
        $path = App::databasePath('migrations/' . $filePath);

        if (! $this->files->exists($path)) {
            $this->files->put($path, $fileContents);
        } else {
            $this->info("File : {$path} already exists");
        }

        return BaseCommand::SUCCESS;
    }

    public function generateMigrationPath(Model $instance): string
    {
        return "_{$this->operation}_locked_columns_to_{$instance->getTable()}.php";
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
        return module_path('Core', "Locking/Stubs/{$this->operation}_locked_column_to_table.php.stub");
    }
}
