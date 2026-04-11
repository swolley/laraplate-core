<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Helpers\HasUniqueFactoryValues;
use Override;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Core\Models\Entity>
 */
final class EntityFactory extends Factory
{
    use HasUniqueFactoryValues;

    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Entity>
     */
    public $model;

    /**
     * Define the model's default state.
     */
    #[Override]
    public function definition(): array
    {
        $entity_type_class = $this->model::getEntityTypeEnumClass();

        return [
            'name' => $this->uniqueValue(fn () => fake()->word(), $this->model, 'name'),
            'type' => fake()->randomElement($entity_type_class::cases()),
        ];
    }
}
