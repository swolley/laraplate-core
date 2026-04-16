<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Modules\Core\Casts\FieldType;
use Modules\Core\Models\Field;
use RuntimeException;
use stdClass;

trait HasDynamicContentFactory
{
    public function dynamicContentDefinition(): array
    {
        $model_name = $this->modelName();

        $entity_type = $model_name::getEntityType();

        $presettables = $model_name::fetchAvailablePresettables($entity_type);

        throw_unless(
            $presettables->isNotEmpty(),
            RuntimeException::class,
            sprintf(
                'No presettables available for model [%s] and entity type [%s]. Ensure CMS/Core seeders created presettables for this type.',
                $model_name,
                $entity_type->toScalar(),
            ),
        );

        $presettable = $presettables->random();

        return [
            'entity_id' => $presettable->entity_id,
            'presettable_id' => $presettable->id,
        ];
    }

    /**
     * Create pivot relations for a content model.
     *
     * @param  Model|Collection<int,Model>  $content
     */
    public function createDynamicContentRelations(Model|Collection $content, ?callable $callback = null): void
    {
        $i = 0;

        try {
            if (! $callback) {
                return;
            }

            if (! $content instanceof Collection) {
                $content = collect([$content]);
            }

            for ($i; $i < $content->count(); $i++) {
                $callback($content[$i]);
            }
        } catch (Exception $e) {
            Log::warning('Failed to attach relations to content: ' . $e->getMessage(), [
                'model_id' => $content instanceof Model ? $content->getKey() : $content->get($i)->getKey(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<string,mixed>  $forcedValues
     */
    private function fillDynamicContents(Model $model, array $forcedValues = []): void
    {
        throw_unless($model->entity_id, RuntimeException::class, 'No entity specified for model: ' . $model::class);

        $model->load('presettable');

        $components_array = $model->presettable->preset->fields->mapWithKeys(function (Field $field) use ($forcedValues): array {
            $value = $field->pivot->default;

            if ($field->pivot->is_required || fake()->boolean()) {
                if (isset($forcedValues[$field->name])) {
                    $value = $forcedValues[$field->name];
                } else {
                    $value = match ($field->type) {
                        FieldType::TEXTAREA => fake()->paragraphs(fake()->numberBetween(1, 3), true),
                        FieldType::TEXT => fake()->text(fake()->numberBetween(100, 255)),
                        FieldType::NUMBER => fake()->randomNumber(),
                        FieldType::EMAIL => fake()->unique()->email(),
                        FieldType::PHONE => fake()->boolean() ? fake()->unique()->e164PhoneNumber() : null,
                        FieldType::URL => fake()->boolean() ? fake()->unique()->url() : null,
                        FieldType::DATETIME => fake()->dateTime()->format('Y-m-d H:i:s'),
                        FieldType::EDITOR => (object) [
                            'blocks' => array_map(static fn (string $paragraph) => (object) [
                                'type' => 'paragraph',
                                'data' => [
                                    'text' => $paragraph,
                                ],
                            ], fake()->paragraphs(fake()->numberBetween(1, 10))),
                        ],
                        FieldType::OBJECT => new stdClass(),
                        default => $value,
                    };
                }
            }

            return [$field->name => $value];
        })->toArray();

        $model->components = $components_array;

        foreach ($model->presettable->preset->fields as $field) {
            if (! ($field->is_translatable ?? false)) {
                $model->{$field->name} = $components_array[$field->name] ?? $field->pivot->default;
            }
        }

        if (
            class_uses_trait($model, HasSlug::class)
            && $model->getRawOriginal('slug') === null
            && method_exists($model, 'generateSlug')
        ) {
            $model->slug = $model->generateSlug();
        }
    }
}
