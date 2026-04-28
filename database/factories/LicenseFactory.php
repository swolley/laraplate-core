<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Helpers\HasUniqueFactoryValues;
use Modules\Core\Models\License;
use Override;

/**
 * @extends Factory<License>
 */
final class LicenseFactory extends Factory
{
    use HasUniqueFactoryValues;

    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = License::class;

    /**
     * Define the model's default state.
     */
    #[Override]
    public function definition(): array
    {
        return [
            'uuid' => fake()->unique()->uuid(),
            'valid_from' => today(),
            'valid_to' => null,
        ];
    }
}
