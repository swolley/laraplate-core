<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\RecordOrigin;
use Override;

/**
 * @extends Factory<RecordOrigin>
 */
final class RecordOriginFactory extends Factory
{
    /**
     * @var class-string<RecordOrigin>
     */
    protected $model = RecordOrigin::class;

    #[Override]
    public function definition(): array
    {
        return [
            'source_key' => fake()->slug(2),
            'source_label' => fake()->company(),
            'external_id' => (string) fake()->randomNumber(6),
            'url' => fake()->url(),
        ];
    }
}
