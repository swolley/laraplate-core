<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modules\Core\Helpers\HasTranslations;
use Override;
use ReflectionClass;
use Throwable;

class TranslationMakeCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    #[Override]
    protected $signature = 'make:translation';

    /**
     * The console command description.
     */
    #[Override]
    protected $description = 'Make a translation model for a given model <fg=yellow>(⚡ Modules\Core)</fg=yellow>';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $all_models = models(false);
        $models_whitout_translations = array_filter($all_models, static fn (string $model): bool => ! Str::contains($model, 'Translation') && ! class_uses_trait($model, HasTranslations::class));
        $model_full_name = select(label: 'Select from available models', options: $models_whitout_translations, required: true) |> (fn (int $choice): string => $models_whitout_translations[$choice]);

        $model_class_name = Str::of($model_full_name)->afterLast('\\')->value();
        $translation_class_name = $model_class_name . 'Translation';
        $translation_full_name = Str::replaceLast($model_class_name, 'Translations\\' . $translation_class_name, $model_full_name);

        $this->info('Creating translation model for ' . $model_full_name . '...');

        ['new_class_path' => $new_class_path, 'new_migration_path' => $new_migration_path] = $this->resolveOutputPaths($model_full_name);

        $model_instance = new $model_full_name();
        $fillables = multiselect('Select the fillable attributes to move to the translation model', [
            ...$model_instance->getFillable(),
        ], required: true);

        $reflection_class = new ReflectionClass($model_full_name);
        $raw_casts = $reflection_class->hasMethod('casts') ? array_filter($reflection_class->getMethod('casts')->invoke($model_instance), fn (string $cast): bool => in_array($cast, $fillables, true), ARRAY_FILTER_USE_KEY) : [];
        $casts = $this->formatCastsForStub($raw_casts);
        $hidden = $this->formatHiddenForStub($reflection_class->hasProperty('hidden') ? $reflection_class->getProperty('hidden')->getDefaultValue() : []);

        $this->info('Filling translation model...');

        $model_table = Str::of($model_class_name)->snake()->plural()->toString();
        $model_relation = Str::singular($model_table);
        $model_fk = $model_relation . '_id';
        $stub = $this->buildTranslationModelStub(
            model_full_name: $model_full_name,
            model_class_name: $model_class_name,
            translation_full_name: $translation_full_name,
            fillables: $fillables,
            casts: $casts,
            hidden: $hidden,
            model_relation: $model_relation,
            model_fk: $model_fk,
        );

        $new_class_file = $new_class_path . $translation_class_name . '.php';

        try {
            if (! file_exists($new_class_path)) {
                mkdir($new_class_path, 0755, true);
            }

            file_put_contents($new_class_file, $stub);
        } catch (Throwable $throwable) {
            $this->error('Error creating translation model: ' . $throwable->getMessage());

            if (file_exists($new_class_file)) {
                unlink($new_class_file);
            }

            return Command::FAILURE;
        }

        $this->info('Translation model created successfully at ' . $new_class_file);

        $this->newLine();
        $this->info('Creating migration...');

        $stub = $this->buildTranslationMigrationStub(
            translation_class_name: $translation_class_name,
            fillables: $fillables,
            raw_casts: $raw_casts,
            model_fk: $model_fk,
            model_table: $model_table,
        );

        $migration_name = now()->format('Y_m_d_His') . '_create_' . Str::of($translation_full_name)->afterLast('\\')->snake()->plural()->toString() . '_table' . '.php';

        file_put_contents($new_migration_path . $migration_name, $stub);
        $this->info('Migration created successfully at ' . $new_migration_path);

        $this->newLine();
        $this->info('Please check both model, migrations and the original model to ensure everything is correct.');

        return Command::SUCCESS;
    }

    /**
     * @return array{new_class_path:string,new_migration_path:string}
     */
    private function resolveOutputPaths(string $model_full_name): array
    {
        if (! Str::startsWith($model_full_name, 'Modules\\')) {
            return [
                'new_class_path' => app_path('Models/Translations/'),
                'new_migration_path' => database_path('migrations/'),
            ];
        }

        $module = Str::of($model_full_name)->after('Modules\\')->before('\\')->toString();
        $models_path = config('modules.paths.generator.model.path');
        $migrations_path = config('modules.paths.generator.migration.path');

        return [
            'new_class_path' => module_path($module, $models_path . '/Translations/'),
            'new_migration_path' => module_path($module, $migrations_path . '/'),
        ];
    }

    /**
     * @param  array<string,string>  $casts
     * @return array<string,string>
     */
    private function formatCastsForStub(array $casts): array
    {
        $formatted = [];

        foreach ($casts as $field => $type) {
            $formatted[$field] = sprintf("'%s' => '%s'", $field, $type);
        }

        return $formatted;
    }

    /**
     * @param  array<int,string>  $hidden
     * @return array<int,string>
     */
    private function formatHiddenForStub(array $hidden): array
    {
        return array_map(static fn (string $field): string => sprintf("'%s'", $field), $hidden);
    }

    /**
     * @param  array<int,string>  $fillables
     * @param  array<string,string>  $casts
     * @param  array<int,string>  $hidden
     */
    private function buildTranslationModelStub(
        string $model_full_name,
        string $model_class_name,
        string $translation_full_name,
        array $fillables,
        array $casts,
        array $hidden,
        string $model_relation,
        string $model_fk,
    ): string {
        $stub = (string) file_get_contents($this->coreResourcePath('stubs/translation.stub'));
        $translation_namespace = Str::beforeLast($translation_full_name, '\\');
        $translation_class_name = Str::afterLast($translation_full_name, '\\');

        $stub = str_replace('[TRANSLATION_NAMESPACE]', $translation_namespace, $stub);
        $stub = str_replace('[TRANSLATION_CLASS_NAME]', $translation_class_name, $stub);
        $stub = str_replace('[MODEL_FULL_NAME]', $model_full_name, $stub);
        $stub = str_replace('[MODEL_CLASS_NAME]', $model_class_name, $stub);
        $stub = str_replace('[MODEL_RELATION]', $model_relation, $stub);
        $stub = str_replace('[MODEL_FK]', $model_fk, $stub);
        $stub = str_replace('[FILLABLE_ATTTRIBUTES]', $this->buildListBlock($fillables), $stub);
        $stub = str_replace('[CASTS_ATTRIBUTES]', $this->buildListBlock($casts), $stub);

        return str_replace('[HIDDEN_ATTRIBUTES]', $this->buildListBlock($hidden), $stub);
    }

    /**
     * @param  array<int|string,string>  $items
     */
    private function buildListBlock(array $items): string
    {
        $block = implode(",\n        ", $items);

        if ($block === '') {
            return '';
        }

        return $block . ',';
    }

    /**
     * @param  array<int,string>  $fillables
     * @param  array<string,string>  $raw_casts
     */
    private function buildTranslationMigrationStub(
        string $translation_class_name,
        array $fillables,
        array $raw_casts,
        string $model_fk,
        string $model_table,
    ): string {
        $stub = (string) file_get_contents($this->coreResourcePath('stubs/translation_migration.stub'));
        $translation_table_name = Str::of($translation_class_name)->snake()->plural()->toString();
        $translated_fields = implode("\n            ", array_map(
            fn (string $fillable): string => sprintf(
                "\$table->%s('%s')->nullable(true)->comment('The translated %s of the model');",
                $this->mapCastToMigrationType($raw_casts[$fillable] ?? 'string'),
                $fillable,
                $fillable,
            ),
            $fillables,
        ));

        $stub = str_replace('[TRANSLATION_TABLE_NAME]', $translation_table_name, $stub);
        $stub = str_replace('[MODEL_FK]', $model_fk, $stub);
        $stub = str_replace('[TRANSLATED_FIELDS]', $translated_fields, $stub);

        return str_replace('[MODEL_TABLE]', $model_table, $stub);
    }

    /**
     * Resolve a file path inside the Core package when module_path points at a different app root.
     */
    private function coreResourcePath(string $relativePath): string
    {
        $relativePath = mb_ltrim(str_replace('\\', '/', $relativePath), '/');
        $via_module = module_path('Core', $relativePath);

        if (is_file($via_module)) {
            return $via_module;
        }

        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    private function mapCastToMigrationType(string $cast): string
    {
        return match ($cast) {
            'int' => 'integer',
            'float' => 'float',
            'boolean' => 'boolean',
            'array', 'object' => 'json',
            'date' => 'date',
            'datetime' => 'datetime',
            default => 'string',
        };
    }
}
