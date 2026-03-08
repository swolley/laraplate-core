<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Core\Helpers\HasTranslations;
use Override;
use ReflectionClass;
use Throwable;

class MakeModelTranslatableCommand extends Command
{
    /**
     * Column type names considered translatable (string-like and JSON types across DB drivers).
     */
    private const array TRANSLATABLE_TYPE_NAMES = [
        'varchar', 'char', 'character', 'character varying', 'bpchar',
        'text', 'tinytext', 'mediumtext', 'longtext',
        'json', 'jsonb',
    ];

    private const array JSON_TYPE_NAMES = ['json', 'jsonb'];

    /**
     * Columns to never propose as translatable.
     */
    private const array EXCLUDED_COLUMNS = [
        'id', 'created_at', 'updated_at', 'deleted_at',
        'locale', 'password', 'remember_token',
        'is_deleted', 'is_locked', 'locked_at', 'locked_by',
        'email_verified_at',
    ];

    #[Override]
    protected $signature = 'make:model-translatable';

    #[Override]
    protected $description = 'Make an existing model translatable with HasTranslations trait, translation table and data migration <fg=yellow>(⚡ Modules\Core)</fg=yellow>';

    public function handle(): int
    {
        $all_models = models(false);
        $available = array_filter(
            $all_models,
            static fn (string $model): bool => ! Str::contains($model, 'Translation')
                && ! class_uses_trait($model, HasTranslations::class),
        );

        if ($available === []) {
            $this->error('No models without HasTranslations trait found.');

            return Command::FAILURE;
        }

        $model_full_name = select(
            label: 'Select the model to make translatable',
            options: $available,
            required: true,
        ) |> (fn (int $choice): string => $available[$choice]);

        $model_instance = new $model_full_name();
        $table_name = $model_instance->getTable();

        if (! Schema::hasTable($table_name)) {
            $this->error("Table '{$table_name}' does not exist. Run migrations first.");

            return Command::FAILURE;
        }

        $this->info("Inspecting table '{$table_name}'...");

        $raw_columns = Schema::getColumns($table_name);
        $translatable = array_values(array_filter(
            $raw_columns,
            $this->isTranslatableColumn(...),
        ));

        if ($translatable === []) {
            $this->error("No translatable (string/text/json) columns found on '{$table_name}'.");

            return Command::FAILURE;
        }

        $options = [];

        foreach ($translatable as $col) {
            $options[$col['name']] = sprintf(
                '%s (%s%s)',
                $col['name'],
                $col['type'],
                $col['nullable'] ? ', nullable' : '',
            );
        }

        $selected_names = multiselect(
            label: 'Select columns to make translatable',
            options: $options,
            required: true,
        );

        $selected_columns = array_values(array_filter(
            $translatable,
            static fn (array $col): bool => in_array($col['name'], $selected_names, true),
        ));

        $model_class_name = class_basename($model_full_name);
        $translation_class_name = $model_class_name . 'Translation';
        $translation_full_name = Str::replaceLast(
            $model_class_name,
            'Translations\\' . $translation_class_name,
            $model_full_name,
        );
        $model_singular = Str::singular($table_name);
        $model_fk = $model_singular . '_id';
        $translation_table = $model_singular . '_translations';

        if (Schema::hasTable($translation_table)) {
            $this->error("Translation table '{$translation_table}' already exists.");

            return Command::FAILURE;
        }

        if (Str::startsWith($model_full_name, 'Modules\\')) {
            $module = Str::of($model_full_name)->after('Modules\\')->before('\\')->toString();
            $models_subpath = config('modules.paths.generator.model.path');
            $new_class_path = module_path($module, $models_subpath . '/Translations/');
            $migrations_subpath = config('modules.paths.generator.migration.path');
            $new_migration_path = module_path($module, $migrations_subpath . '/');
        } else {
            $new_class_path = app_path('Models/Translations/');
            $new_migration_path = database_path('migrations/');
        }

        $this->newLine();
        $this->info('Summary of changes:');
        $this->line("  Translation model : {$translation_full_name}");
        $this->line("  Translation table : {$translation_table}");
        $this->line("  Foreign key       : {$model_fk}");
        $this->line('  Translatable cols : ' . implode(', ', $selected_names));
        $this->newLine();

        if (! confirm('Proceed?', true)) {
            $this->info('Aborted.');

            return Command::SUCCESS;
        }

        $this->newLine();
        $this->info('Creating translation model...');

        $result = $this->createTranslationModel(
            $model_full_name,
            $model_class_name,
            $translation_full_name,
            $translation_class_name,
            $model_fk,
            $model_singular,
            $selected_columns,
            $new_class_path,
        );

        if ($result !== Command::SUCCESS) {
            return $result; // @codeCoverageIgnore
        }

        $this->info('Creating migration...');

        $this->createTranslatableMigration(
            $table_name,
            $translation_table,
            $model_fk,
            $model_singular,
            $selected_columns,
            $new_migration_path,
        );

        $this->info('Adding HasTranslations trait to model...');
        $this->addTraitToModel($model_full_name, $selected_names);

        $this->newLine();
        $this->info('All done! Review the generated files then run `php artisan migrate`.');

        return Command::SUCCESS;
    }

    private function isTranslatableColumn(array $column): bool
    {
        if (in_array($column['name'], self::EXCLUDED_COLUMNS, true)) {
            return false;
        }

        if (str_ends_with((string) $column['name'], '_id')) {
            return false;
        }

        if ($column['auto_increment']) {
            return false;
        }

        return in_array($column['type_name'], self::TRANSLATABLE_TYPE_NAMES, true);
    }

    private function createTranslationModel(
        string $model_full_name,
        string $model_class_name,
        string $translation_full_name,
        string $translation_class_name,
        string $model_fk,
        string $model_singular,
        array $selected_columns,
        string $new_class_path,
    ): int {
        $stub = file_get_contents(module_path('Core', 'stubs/translation.stub'));

        $translation_namespace = Str::beforeLast($translation_full_name, '\\');
        $fillable_names = array_map(fn (array $col) => $col['name'], $selected_columns);

        $fillable_str = implode(",\n        ", array_map(
            fn ($name): string => "'{$name}'",
            $fillable_names,
        ));

        if ($fillable_str !== '') {
            $fillable_str .= ',';
        }

        $casts = [];

        foreach ($selected_columns as $col) {
            if (in_array($col['type_name'], self::JSON_TYPE_NAMES, true)) {
                $casts[$col['name']] = 'array';
            }
        }

        $casts_str = implode(",\n            ", array_map(
            fn ($field, $type): string => "'{$field}' => '{$type}'",
            array_keys($casts),
            $casts,
        ));

        if ($casts_str !== '') {
            $casts_str .= ',';
        }

        $reflection = new ReflectionClass($model_full_name);
        $model_hidden = $reflection->hasProperty('hidden')
            ? $reflection->getProperty('hidden')->getDefaultValue()
            : [];
        $translation_hidden = array_intersect($model_hidden, $fillable_names);
        $hidden_str = implode(",\n        ", array_map(
            fn ($h): string => "'{$h}'",
            $translation_hidden,
        ));

        if ($hidden_str !== '') {
            $hidden_str .= ',';
        }

        $stub = str_replace('[TRANSLATION_NAMESPACE]', $translation_namespace, $stub);
        $stub = str_replace('[TRANSLATION_CLASS_NAME]', $translation_class_name, $stub);
        $stub = str_replace('[MODEL_FULL_NAME]', $model_full_name, $stub);
        $stub = str_replace('[MODEL_CLASS_NAME]', $model_class_name, $stub);
        $stub = str_replace('[MODEL_RELATION]', $model_singular, $stub);
        $stub = str_replace('[MODEL_FK]', $model_fk, $stub);
        $stub = str_replace('[FILLABLE_ATTTRIBUTES]', $fillable_str, $stub);
        $stub = str_replace('[CASTS_ATTRIBUTES]', $casts_str, $stub);
        $stub = str_replace('[HIDDEN_ATTRIBUTES]', $hidden_str, $stub);
        $stub = str_replace('[main_relation]', $model_singular, $stub);
        $stub = str_replace('BelongsTo<Author>', 'BelongsTo<' . $model_class_name . '>', $stub);

        $stub = str_replace(
            'extends Model',
            'extends Model implements ITranslated',
            $stub,
        );

        $stub = str_replace(
            "use {$model_full_name};",
            "use {$model_full_name};\nuse Modules\\Core\\Services\\Translation\\Definitions\\ITranslated;",
            $stub,
        );

        $new_class_file = $new_class_path . $translation_class_name . '.php';

        try {
            if (! file_exists($new_class_path)) {
                mkdir($new_class_path, 0755, true);
            }

            file_put_contents($new_class_file, $stub);
        } catch (Throwable $e) { // @codeCoverageIgnoreStart
            $this->error('Error creating translation model: ' . $e->getMessage());

            if (file_exists($new_class_file)) {
                unlink($new_class_file);
            }

            return Command::FAILURE;
        } // @codeCoverageIgnoreEnd

        $this->line("  Created: {$new_class_file}");

        return Command::SUCCESS;
    }

    private function createTranslatableMigration(
        string $table_name,
        string $translation_table,
        string $model_fk,
        string $model_singular,
        array $selected_columns,
        string $migration_path,
    ): void {
        $stub = file_get_contents(module_path('Core', 'stubs/make_model_translatable_migration.stub'));

        $i3 = str_repeat(' ', 12);
        $i4 = str_repeat(' ', 16);
        $i5 = str_repeat(' ', 20);
        $i7 = str_repeat(' ', 28);

        $translated_fields = implode("\n", array_map(
            fn (array $col): string => $i3 . $this->columnToBlueprintCode($col),
            $selected_columns,
        ));

        $insert_fields = implode("\n", array_map(
            fn (array $col): string => "{$i5}'{$col['name']}' => \$row->{$col['name']},",
            $selected_columns,
        ));

        $col_names_list = implode("\n", array_map(
            fn (array $col): string => "{$i4}'{$col['name']}',",
            $selected_columns,
        ));
        $drop_columns = "{$i3}\$table->dropColumn([\n{$col_names_list}\n{$i3}]);";

        $restore_columns = implode("\n", array_map(
            fn (array $col): string => $i3 . $this->columnToBlueprintCode($col),
            $selected_columns,
        ));

        $restore_fields = implode("\n", array_map(
            fn (array $col): string => "{$i7}'{$col['name']}' => \$translation->{$col['name']},",
            $selected_columns,
        ));

        $stub = str_replace('[TRANSLATION_TABLE_NAME]', $translation_table, $stub);
        $stub = str_replace('[MODEL_FK]', $model_fk, $stub);
        $stub = str_replace('[MODEL_TABLE]', $table_name, $stub);
        $stub = str_replace('[MODEL_SINGULAR]', $model_singular, $stub);
        $stub = str_replace('[TRANSLATED_FIELDS]', $translated_fields, $stub);
        $stub = str_replace('[INSERT_FIELDS]', $insert_fields, $stub);
        $stub = str_replace('[DROP_COLUMNS]', $drop_columns, $stub);
        $stub = str_replace('[RESTORE_COLUMNS]', $restore_columns, $stub);
        $stub = str_replace('[RESTORE_FIELDS]', $restore_fields, $stub);

        $migration_name = now()->format('Y_m_d_His')
            . '_create_'
            . $translation_table
            . '_table.php';

        file_put_contents($migration_path . $migration_name, $stub);

        $this->line("  Created: {$migration_path}{$migration_name}");
    }

    private function addTraitToModel(string $model_full_name, array $field_names): void
    {
        $reflection = new ReflectionClass($model_full_name);
        $file_path = $reflection->getFileName();

        if (! $file_path || ! file_exists($file_path)) {
            $this->warn('Could not determine model file path. Please add HasTranslations trait manually.');

            return;
        }

        $content = file_get_contents($file_path);

        $import_line = 'use Modules\\Core\\Helpers\\HasTranslations;';

        if (! str_contains($content, $import_line)) {
            preg_match_all('/^use [^;]+;$/m', $content, $matches, PREG_OFFSET_CAPTURE);

            $class_pos = false;

            foreach (["\nfinal class ", "\nabstract class ", "\nclass "] as $prefix) {
                $pos = mb_strpos($content, $prefix);

                if ($pos !== false) {
                    $class_pos = $pos;

                    break;
                }
            }

            if (isset($matches[0]) && $matches[0] !== [] && $class_pos !== false) {
                $last_import = null;

                foreach ($matches[0] as $match) {
                    if ($match[1] < $class_pos) {
                        $last_import = $match;
                    }
                }

                if ($last_import !== null) {
                    $insert_pos = $last_import[1] + mb_strlen($last_import[0]);
                    $content = mb_substr($content, 0, $insert_pos)
                        . "\n" . $import_line
                        . mb_substr($content, $insert_pos);
                }
            }
        }

        $trait_usage = 'use HasTranslations;';

        if (! str_contains($content, $trait_usage) && preg_match('/(class\s+\w+[^{]*\{\s*\n)/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $insert_pos = $matches[1][1] + mb_strlen($matches[1][0]);
            $content = mb_substr($content, 0, $insert_pos)
                . "    {$trait_usage}\n"
                . mb_substr($content, $insert_pos);
        }

        $content = $this->removeFieldsFromModel($content, $field_names);

        file_put_contents($file_path, $content);

        $this->line("  Updated: {$file_path}");
        $this->warn('  Please review the model for any remaining cleanup (hidden, appends, etc.).');
    }

    private function removeFieldsFromModel(string $content, array $field_names): string
    {
        $lines = explode("\n", $content);
        $context = 'normal';
        $depth = 0;
        $seen_bracket = false;
        $result = [];

        foreach ($lines as $line) {
            $trimmed = mb_trim($line);

            if ($context === 'normal') {
                if (str_contains($line, '$fillable') && str_contains($line, '=')) {
                    $context = 'fillable';
                    $depth = 0;
                    $seen_bracket = false;
                } elseif (str_contains($line, 'function casts(') || (str_contains($line, '$casts') && str_contains($line, '='))) {
                    $context = 'casts';
                    $depth = 0;
                    $seen_bracket = false;
                }
            }

            if ($context !== 'normal') {
                $open = mb_substr_count($line, '[') + mb_substr_count($line, '{');
                $close = mb_substr_count($line, ']') + mb_substr_count($line, '}');
                $depth += $open - $close;

                if ($open > 0 || $close > 0) {
                    $seen_bracket = true;
                }
            }

            $should_skip = false;

            if ($context !== 'normal') {
                foreach ($field_names as $name) {
                    if (preg_match("/'" . preg_quote((string) $name, '/') . "'/", $trimmed)) {
                        $should_skip = true;

                        break;
                    }
                }
            }

            if ($context !== 'normal' && $seen_bracket && $depth <= 0) {
                $context = 'normal';
            }

            if (! $should_skip) {
                $result[] = $line;
            }
        }

        return implode("\n", $result);
    }

    private function columnToBlueprintCode(array $column): string
    {
        $name = $column['name'];
        $type_name = $column['type_name'];
        $full_type = $column['type'];
        $nullable = $column['nullable'];

        $method = match ($type_name) {
            'varchar', 'character varying' => 'string',
            'char', 'character', 'bpchar' => 'char',
            'tinytext' => 'tinyText',
            'text' => 'text',
            'mediumtext' => 'mediumText',
            'longtext' => 'longText',
            'json', 'jsonb' => 'json',
            default => 'string',
        };

        $code = "\$table->{$method}('{$name}'";

        if (
            in_array($method, ['string', 'char'], true)
            && preg_match('/\((\d+)\)/', (string) $full_type, $matches)
        ) {
            $length = (int) $matches[1];

            if (! ($method === 'string' && $length === 255)) {
                $code .= ", {$length}";
            }
        }

        $code .= ')->nullable(' . ($nullable ? 'true' : 'false') . ')';

        return $code . "->comment('The translated {$name}');";
    }
}
