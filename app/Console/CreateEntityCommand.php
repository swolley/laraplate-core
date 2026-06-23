<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\CMS\Models\Preset;
use Modules\Core\Casts\FieldType;
use Modules\Core\Contracts\IDynamicEntityTypable;
use Modules\Core\Console\Concerns\HasCommandUtils;
use Modules\Core\Models\Entity;
use Modules\Core\Models\Field;
use Modules\Core\Overrides\Command;
use Override;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

final class CreateEntityCommand extends Command
{
    use HasCommandUtils;

    /**
     * @var list<string>
     */
    private const PROMPTED_ENTITY_ATTRIBUTES = ['name', 'slug', 'type'];

    /**
     * The name and signature of the console command.
     */
    #[Override]
    protected $signature = 'model:create-entity {entity?} {--content-model}';

    /**
     * The console command description.
     */
    #[Override]
    protected $description = 'Create new CMS entity <fg=cyan>(📰 Modules/CMS)</fg=cyan>';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        DB::transaction(function (): void {
            $module = $this->resolveModule();

            /** @var class-string<Entity> $entity_model_class */
            $entity_model_class = "Modules\\{$module}\\Models\\Entity";
            $entity = new $entity_model_class();

            /** @var class-string<IDynamicEntityTypable> $entity_type_class */
            $entity_type_class = $this->resolveEntityTypeClass($entity_model_class);

            if ($this->argument('entity')) {
                $entity->name = (string) $this->argument('entity');
            }

            $fillables = $entity->getFillable();
            $validations = $entity->getOperationRules('create');

            /** @var EloquentCollection<int,Field> $all_fields */
            $all_fields = Field::query()->get()->keyBy('id');

            foreach (self::PROMPTED_ENTITY_ATTRIBUTES as $attribute) {
                if (! in_array($attribute, $fillables, true)) {
                    continue;
                }
                if ($attribute === 'name' && $entity->name) {
                    continue;
                }

                if ($attribute === 'type') {
                    $selected_type = select('Choose the type of the entity', $entity_type_class::values(), required: true);
                    $entity->type = $entity_type_class::from((string) $selected_type);

                    continue;
                }

                $entity->{$attribute} = text(
                    ucfirst($attribute),
                    '',
                    $attribute === 'slug' ? Str::slug((string) $entity->name) : '',
                    true,
                    fn (string $value): ?string => $this->validationCallback($attribute, $value, $validations),
                );
            }

            $entity->save();

            $this->output->info(sprintf("A default preset 'standard' will be created for the entity '%s'", $entity->name));

            $preset = new Preset();
            $preset->name = 'standard';
            $preset->entity_id = $entity->id;
            $preset->save();

            $preset_fields = multiselect('Choose fields for the preset', $all_fields->pluck('name', 'id'), required: true);

            foreach ($preset_fields as $field) {
                $field = $all_fields->get($field);
                $is_required = confirm(sprintf("Do you want '%s' to be required?", $field->name), false);
                $this->assignFieldToPreset($preset, $field, $is_required);
            }

            $this->info(sprintf("Entity '%s' created", $entity->name));
        });
    }

    /**
     * Get the console command arguments.
     */
    #[Override]
    protected function getArguments(): array
    {
        return [
            ['entity', InputArgument::OPTIONAL, 'The entity name.'],
        ];
    }

    /**
     * Get the console command options.
     */
    #[Override]
    protected function getOptions(): array
    {
        return [
            ['content-model', '', InputOption::VALUE_NONE, 'Create a content model file for this entity.', false],
        ];
    }

    private function resolveModule(): string
    {
        if (class_exists(\Modules\CMS\Models\Entity::class)) {
            return 'CMS';
        }

        $valid_modules = array_values(array_filter(
            modules(filter: function (string $module): bool {
                if (in_array($module, ['Core', 'App'], true)) {
                    return false;
                }

                $entity_class = "Modules\\{$module}\\Models\\Entity";

                return class_exists($entity_class);
            }),
        ));

        if ($valid_modules === []) {
            throw new RuntimeException('No module with an Entity model found.');
        }

        if (count($valid_modules) === 1) {
            return $valid_modules[0];
        }

        return select('Choose the module', $valid_modules, required: true);
    }

    /**
     * @param  class-string<Entity>  $entity_model_class
     * @return class-string<IDynamicEntityTypable>
     */
    private function resolveEntityTypeClass(string $entity_model_class): string
    {
        $method = (new ReflectionClass($entity_model_class))->getMethod('getEntityTypeEnumClass');
        $method->setAccessible(true);

        /** @var class-string<IDynamicEntityTypable> $entity_type_class */
        $entity_type_class = $method->invoke(null);

        return $entity_type_class;
    }

    private function assignFieldToPreset(Preset $preset, Field $field, bool $is_required): void
    {
        $pivotAttributes = [
            'preset_id' => $preset->id,
            'is_required' => $is_required,
            'default' => $this->getDefaultFieldValue($field, $is_required),
        ];
        $preset->fields()->attach($field->id, $pivotAttributes);
    }

    private function getDefaultFieldValue(Field $field, bool $is_required): mixed
    {
        $default = text(
            sprintf("Specify a default value for '%s'", $field->name),
            required: $is_required,
            validate: null,
            hint: match (true) {
                $field->type === FieldType::Select && isset($field->options->multiple) && $field->options->multiple => 'Use [] for an empty selection; numbers, booleans, or JSON arrays as needed.',
                $field->type === FieldType::Switch => $is_required
                    ? 'Enter true or false (required).'
                    : 'Enter true or false.',
                $field->type === FieldType::Checkbox => 'Use [] for an empty list; JSON array syntax otherwise.',
                default => "Type 'null' to set the default value to null.",
            },
        );

        if ($default === 'null') {
            $default = null;
        } elseif (preg_match('/\d+/', $default)) {
            $default = Str::contains($default, '.') ? (float) $default : (int) $default;
        } elseif (in_array($default, ['true', 'false'], true)) {
            $default = $default === 'true';
        } elseif (preg_match('/^\[.*]$/', $default)) {
            $default = json_decode($default);
        }

        return $default;
    }
}
