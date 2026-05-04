<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Helpers\HasUniqueFactoryValues;
use Modules\Core\Models\Role;
use Override;

/**
 * @extends Factory<Role>
 */
final class RoleFactory extends Factory
{
    use HasUniqueFactoryValues;

    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array<int,mixed|string>
     *
     * @psalm-return array{name: string, guard_name: string, description: string}
     */
    #[Override]
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'guard_name' => (string) config('auth.defaults.guard', 'web'),
            'description' => fake()->optional()->text(),
        ];
    }
}
