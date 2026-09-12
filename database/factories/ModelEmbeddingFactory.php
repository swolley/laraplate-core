<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\ModelEmbedding;
use Override;

/**
 * @extends Factory<ModelEmbedding>
 */
final class ModelEmbeddingFactory extends Factory
{
    /**
     * @var class-string<ModelEmbedding>
     */
    protected $model = ModelEmbedding::class;

    /**
     * Define the model's default state.
     */
    #[Override]
    public function definition(): array
    {
        return [
            'embedding' => [
                fake()->randomFloat(4, -1, 1),
                fake()->randomFloat(4, -1, 1),
                fake()->randomFloat(4, -1, 1),
            ],
            // Provenance columns default to null: legacy/non-translated vectors.
            'locale' => null,
            'model_key' => null,
        ];
    }

    /**
     * model_type/model_id are the morph target and are intentionally not
     * mass-assignable (not in $fillable); set them after construction so a bare
     * ModelEmbedding::factory()->create() still satisfies the NOT NULL morph columns
     * without needing a real owning model.
     */
    #[Override]
    public function configure(): self
    {
        return $this->afterMaking(function (ModelEmbedding $modelEmbedding): void {
            if ($modelEmbedding->model_type === null) {
                $modelEmbedding->forceFill([
                    'model_type' => ModelEmbedding::class,
                    'model_id' => fake()->randomNumber(),
                ]);
            }
        });
    }
}
