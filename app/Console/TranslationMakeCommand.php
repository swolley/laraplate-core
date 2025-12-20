<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modules\Core\Helpers\HasTranslations;
use ReflectionClass;
use Throwable;

class TranslationMakeCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'make:translation';

    /**
     * The console command description.
     */
    protected $description = 'Make a translation model for a given model <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

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

        if (Str::startsWith($model_full_name, 'Modules\\')) {
            $module = Str::of($model_full_name)->after('Modules\\')->before('\\')->toString();
            $models_path = config('modules.paths.generator.model.path');
            $new_class_path = module_path($module, $models_path . '/Translations/');
            $migrations_path = config('modules.paths.generator.migration.path');
            $new_migration_path = module_path($module, $migrations_path . '/');
        } else {
            $new_class_path = app_path('Models/Translations/');
            $new_migration_path = database_path('migrations/');
        }

        $model_instance = new $model_full_name();
        $fillables = multiselect('Select the fillable attributes to move to the translation model', [
            ...$model_instance->getFillable(),
        ], required: true);

        $reflection_class = new ReflectionClass($model_full_name);
        $casts = $reflection_class->hasMethod('casts') ? array_filter($reflection_class->getMethod('casts')->invoke($model_instance), fn (string $cast): bool => in_array($cast, $fillables, true), ARRAY_FILTER_USE_KEY) : [];

        foreach ($casts as $field => &$type) {
            $type = sprintf("'%s' => '%s'", $field, $type);
        }

        $hidden = $reflection_class->hasProperty('hidden') ? $reflection_class->getProperty('hidden')->getDefaultValue() : [];

        foreach ($hidden as &$field) {
            $field = sprintf("'%s'", $field);
        }

        $this->info('Filling translation model...');

        // model stub
        $stub = file_get_contents(module_path('Core', 'stubs/translation.stub'));

        $translation_namespace = Str::beforeLast($translation_full_name, '\\');
        $translation_class_name = Str::afterLast($translation_full_name, '\\');
        $model_table = Str::of($model_class_name)->snake()->plural()->toString();
        $model_relation = Str::singular($model_table);
        $model_fk = $model_relation . '_id';

        $stub = str_replace('[TRANSLATION_NAMESPACE]', $translation_namespace, $stub);
        $stub = str_replace('[TRANSLATION_CLASS_NAME]', $translation_class_name, $stub);
        $stub = str_replace('[MODEL_FULL_NAME]', $model_full_name, $stub);
        $stub = str_replace('[MODEL_CLASS_NAME]', $model_class_name, $stub);
        $stub = str_replace('[MODEL_RELATION]', $model_relation, $stub);
        $stub = str_replace('[MODEL_FK]', $model_fk, $stub);

        // fillables
        $fillable_attributes = implode(",\n        ", array_map(static fn (string $fillable): string => "'" . $fillable . "'", $fillables));

        if ($fillable_attributes !== '') {
            $fillable_attributes .= ',';
        }
        $stub = str_replace('[FILLABLE_ATTTRIBUTES]', $fillable_attributes, $stub);

        // casts
        $casts_attributes = implode(",\n        ", $casts);

        if ($casts_attributes !== '') {
            $casts_attributes .= ',';
        }
        $stub = str_replace('[CASTS_ATTRIBUTES]', $casts_attributes, $stub);

        // hidden
        $hidden_attributes = implode(",\n        ", $hidden);

        if ($hidden_attributes !== '') {
            $hidden_attributes .= ',';
        }
        $stub = str_replace('[HIDDEN_ATTRIBUTES]', $hidden_attributes, $stub);

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

        // migration stub
        $stub = file_get_contents(module_path('Core', 'stubs/translation_migration.stub'));

        $translation_table_name = Str::of($translation_class_name)->snake()->plural()->toString();
        $translated_fields = implode("\n            ", array_map(function (string $fillable) use ($casts): string {
            $type = match ($casts[$fillable] ?? 'string') {
                'int' => 'integer',
                'float' => 'float',
                'boolean' => 'boolean',
                'array' => 'json',
                'object' => 'json',
                'date' => 'date',
                'datetime' => 'datetime',
                default => 'string',
            };

            return "\$table->{$type}('" . $fillable . "')->nullable(true)->comment('The translated " . $fillable . " of the model');";
        }, $fillables));

        $stub = file_get_contents(module_path('Core', 'stubs/translation_migration.stub'));
        $stub = str_replace('[TRANSLATION_TABLE_NAME]', $translation_table_name, $stub);
        $stub = str_replace('[MODEL_FK]', $model_fk, $stub);
        $stub = str_replace('[TRANSLATED_FIELDS]', $translated_fields, $stub);
        $stub = str_replace('[MODEL_TABLE]', $model_table, $stub);

        $migration_name = now()->format('Y_m_d_His') . '_create_' . Str::of($translation_full_name)->afterLast('\\')->snake()->plural()->toString() . '_table' . '.php';

        file_put_contents($new_migration_path . $migration_name, $stub);
        $this->info('Migration created successfully at ' . $new_migration_path);

        $this->newLine();
        $this->info('Please check both model, migrations and the original model to ensure everything is correct.');

        return Command::SUCCESS;
    }
}
